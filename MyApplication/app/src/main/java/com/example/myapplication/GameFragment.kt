package com.example.myapplication

import android.Manifest
import android.annotation.SuppressLint
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothManager
import android.bluetooth.le.ScanCallback
import android.bluetooth.le.ScanResult
import android.bluetooth.le.ScanSettings
import android.content.Context
import android.content.Intent
import android.content.pm.ActivityInfo
import android.content.pm.PackageManager
import android.graphics.Color
import android.graphics.Matrix
import android.graphics.RectF
import android.graphics.drawable.GradientDrawable
import android.os.Build
import android.os.Bundle
import android.os.Looper
import android.util.Log
import android.view.Gravity
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.FrameLayout
import android.widget.TextView
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.content.ContextCompat
import androidx.core.view.isVisible
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.navArgs
import com.bumptech.glide.Glide
import com.bumptech.glide.load.engine.DiskCacheStrategy
import com.bumptech.glide.request.RequestOptions
import com.example.myapplication.databinding.FragmentGameBinding
import com.google.android.gms.location.*
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlin.math.pow

class GameFragment : Fragment() {

    private var _binding: FragmentGameBinding? = null
    private val binding get() = _binding!!

    private val args: GameFragmentArgs by navArgs()

    private lateinit var fusedLocationClient: FusedLocationProviderClient
    private lateinit var locationCallback: LocationCallback
    private var rosterRefreshJob: Job? = null
    private var bleStatusJob: Job? = null
    private var apiScanJob: Job? = null

    private val bluetoothAdapter: BluetoothAdapter? by lazy {
        val manager = context?.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager
        manager?.adapter
    }

    private data class BeaconReading(
        val key: Int, val id: Int, val floor: Int, val rssi: Int, val lastSeen: Long, val serverUuid: String
    )
    private val activeBeacons = mutableMapOf<Int, BeaconReading>()

    private var currentFloor = "--"
    private var displayedFloor = ""
    private var isMapLoading = false

    private var currentMapWidth: Double = 0.0
    private var currentMapHeight: Double = 0.0

    private data class MapBeacon(val info: BeaconInfo, val floor: Int, val id: Int)
    private var allMatchBeacons = mutableListOf<MapBeacon>()

    private var calculatedX: Double? = null
    private var calculatedY: Double? = null

    private val idRegex = Regex("""B(\d+)""", RegexOption.IGNORE_CASE)
    private val floorRegex = Regex("""A(\d+)""", RegexOption.IGNORE_CASE)

    private val permissionsLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { startLocationUpdates(); startBleScan() }

    private val bluetoothEnableLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { _ -> if (bluetoothAdapter?.isEnabled == true) startBleScan() }

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentGameBinding.inflate(inflater, container, false)
        fusedLocationClient = LocationServices.getFusedLocationProviderClient(requireActivity())
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        binding.teamNameTextView.text = if (args.team.equals("A", ignoreCase = true)) "Terrorista" else "Contra Terrorista"
        
        // Estilo visual do teu ponto
        binding.userDot.background = GradientDrawable().apply {
            shape = GradientDrawable.OVAL
            setColor(Color.RED)
            setStroke(2, Color.WHITE)
        }
        binding.userDot.elevation = 15f

        locationCallback = object : LocationCallback() { override fun onLocationResult(locationResult: LocationResult) {} }
    }

    private fun loadMap(floor: Int) {
        if (_binding == null || isMapLoading) return
        isMapLoading = true
        binding.mapLoader.isVisible = true

        viewLifecycleOwner.lifecycleScope.launch {
            try {
                val response = RetrofitInstance.api.getMaps(args.matchId)
                if (response.isSuccessful && response.body()?.ok == true) {
                    val maps = response.body()?.maps ?: emptyList()
                    val newMatchBeacons = mutableListOf<MapBeacon>()
                    maps.forEach { map ->
                        map.beacons?.forEach { bInfo ->
                            // CORREÇÃO: Remove letras do label (ex: "B1" vira 1) para bater com o Bluetooth
                            val bId = bInfo.label?.replace(Regex("[^0-9]"), "")?.toIntOrNull() 
                                     ?: bInfo.uuid.substringAfterLast("-B").filter { it.isDigit() }.toIntOrNull() ?: 0
                            newMatchBeacons.add(MapBeacon(bInfo, map.floor, bId))
                        }
                    }
                    allMatchBeacons = newMatchBeacons

                    val mapForFloor = maps.firstOrNull { it.floor == floor }
                    _binding?.let { b ->
                        if (mapForFloor != null) {
                            currentMapWidth = mapForFloor.width ?: 0.0
                            currentMapHeight = mapForFloor.height ?: 0.0
                            val url = mapForFloor.mapUrl.replace("http://central-app.local", RetrofitInstance.BASE_URL.removeSuffix("/"))
                            
                            Glide.with(this@GameFragment)
                                .load(url)
                                .apply(RequestOptions().diskCacheStrategy(DiskCacheStrategy.ALL).dontTransform())
                                .into(b.mapImageView)
                            
                            displayedFloor = floor.toString()
                        }
                    }
                }
            } catch (e: Exception) { Log.e("GameFragment", "Map load error", e) } 
            finally { _binding?.let { isMapLoading = false; it.mapLoader.isVisible = false } }
        }
    }

    private val scanCallback = object : ScanCallback() {
        @SuppressLint("MissingPermission")
        override fun onScanResult(callbackType: Int, result: ScanResult) {
            val name = result.device.name ?: result.scanRecord?.deviceName ?: ""
            val idMatch = idRegex.find(name)
            val floorMatch = floorRegex.find(name)

            if (idMatch != null) {
                val id = idMatch.groupValues[1].toInt()
                val floor = floorMatch?.groupValues[1]?.toInt() ?: 0
                val key = (floor * 1000) + id
                
                // Procurar na lista da BD limpa
                val mb = allMatchBeacons.find { it.id == id && it.floor == floor }
                val serverUuid = mb?.info?.uuid ?: result.device.address
                
                activeBeacons[key] = BeaconReading(key, id, floor, result.rssi, System.currentTimeMillis(), serverUuid)
            }
        }
    }

    private fun updateStatusUI() {
        val b = _binding ?: return
        val now = System.currentTimeMillis()
        activeBeacons.entries.removeIf { now - it.value.lastSeen > 8000 }

        val strongest = activeBeacons.values.maxByOrNull { it.rssi }
        currentFloor = if (strongest != null && strongest.rssi > -127) strongest.floor.toString() else "--"

        b.bleStatusTextView.text = activeBeacons.values.sortedBy { it.id }.joinToString(" | ") { 
            String.format("B%d: %d dBm", it.id, it.rssi) 
        }.ifEmpty { "Sinal: --" }
        
        if (currentFloor != "--" && currentFloor != displayedFloor && !isMapLoading) loadMap(currentFloor.toInt())
        
        // Tenta redesenhar sempre que o sinal muda
        b.mapImageView.post { updateUserDotPosition() }
    }

    private fun updateUserDotPosition() {
        val b = _binding ?: return
        if (currentMapWidth <= 0 || currentMapHeight <= 0 || activeBeacons.isEmpty()) { 
            b.userDot.isVisible = false ; return 
        }

        val rect = getImageBounds(b.mapImageView)
        if (rect.width() <= 0) return

        var totalWeight = 0.0 ; var wX = 0.0 ; var wY = 0.0
        activeBeacons.values.filter { it.floor.toString() == currentFloor }.forEach { reading ->
            allMatchBeacons.find { it.id == reading.id && it.floor == reading.floor }?.let { mb ->
                val weight = (reading.rssi + 130).toDouble().coerceAtLeast(1.0).pow(3)
                wX += mb.info.x * weight ; wY += mb.info.y * weight ; totalWeight += weight
            }
        }

        if (totalWeight > 0) {
            calculatedX = wX / totalWeight ; calculatedY = wY / totalWeight
            
            // CONVERSÃO PIXEL/METRO
            val pX = (calculatedX!! / currentMapWidth * rect.width()).toFloat()
            val pY = (calculatedY!! / currentMapHeight * rect.height()).toFloat()
            
            b.userDot.translationX = rect.left + pX - (b.userDot.width / 2f)
            b.userDot.translationY = rect.top + pY - (b.userDot.height / 2f)
            b.userDot.isVisible = true
            b.userDot.bringToFront()
        } else { b.userDot.isVisible = false }
    }

    private fun getImageBounds(imageView: android.widget.ImageView): RectF {
        val drawable = imageView.drawable ?: return RectF()
        val values = FloatArray(9)
        imageView.imageMatrix.getValues(values)
        val left = values[Matrix.MTRANS_X]
        val top = values[Matrix.MTRANS_Y]
        val width = drawable.intrinsicWidth * values[Matrix.MSCALE_X]
        val height = drawable.intrinsicHeight * values[Matrix.MSCALE_Y]
        return RectF(left, top, left + width, top + height)
    }

    private suspend fun sendScanToServer() {
        if (activeBeacons.isEmpty()) return
        val readings = activeBeacons.values.map { ScanReading(it.serverUuid, 0, 0, it.rssi) }
        try {
            val teamId = if (args.team.equals("A", true)) 1 else 2
            RetrofitInstance.api.sendScan("Bearer ${args.token}", ScanRequest(args.matchId, teamId, args.playerId, 1, if (currentFloor == "--") 0 else currentFloor.toInt(), calculatedX, calculatedY, readings))
        } catch (e: Exception) { Log.e("GameFragment", "API error", e) }
    }

    private fun startBleScan() {
        val adapter = bluetoothAdapter ?: return
        if (!adapter.isEnabled) { bluetoothEnableLauncher.launch(Intent(BluetoothAdapter.ACTION_REQUEST_ENABLE)); return }
        try {
            val settings = ScanSettings.Builder().setScanMode(ScanSettings.SCAN_MODE_LOW_LATENCY).setMatchMode(ScanSettings.MATCH_MODE_AGGRESSIVE).build()
            adapter.bluetoothLeScanner?.startScan(null, settings, scanCallback)
        } catch(e: Exception) { }
    }

    override fun onResume() {
        super.onResume()
        activity?.requestedOrientation = ActivityInfo.SCREEN_ORIENTATION_LANDSCAPE
        checkPermissionsAndStartServices()
        if (displayedFloor == "") loadMap(0)
        rosterRefreshJob = viewLifecycleOwner.lifecycleScope.launch { while (isActive) { loadTeamRoster(); delay(8000) } }
        bleStatusJob = viewLifecycleOwner.lifecycleScope.launch { while (isActive) { updateStatusUI(); delay(1000) } }
        apiScanJob = viewLifecycleOwner.lifecycleScope.launch { while (isActive) { sendScanToServer(); delay(1000) } }
    }

    private suspend fun loadTeamRoster() {
        try {
            val response = RetrofitInstance.api.getTeamRoster("Bearer ${args.token}", args.matchId, args.team)
            if (response.isSuccessful) {
                val players = response.body()?.players ?: emptyList()
                _binding?.let { b ->
                    b.playerListContainer.removeAllViews()
                    players.forEach { p ->
                        val tv = TextView(context).apply { text = "- ${p.name}"; setTextColor(Color.WHITE); textSize = 16f }
                        b.playerListContainer.addView(tv)
                    }
                    drawPlayersOnMap(players)
                }
            }
        } catch (e: Exception) { }
    }

    private fun drawPlayersOnMap(players: List<PlayerInfo>) {
        val b = _binding ?: return
        b.playersContainer.removeAllViews()
        val rect = getImageBounds(b.mapImageView)
        if (rect.width() <= 0 || currentMapWidth <= 0) return
        val floorInt = currentFloor.toIntOrNull() ?: -1

        players.forEach { p ->
            if (p.id != args.playerId && p.floor == floorInt && p.x != null && p.y != null) {
                val playerView = FrameLayout(requireContext()).apply { layoutParams = FrameLayout.LayoutParams(-2, -2) }
                val dot = View(context).apply { layoutParams = FrameLayout.LayoutParams(24, 24).apply { gravity = 17 }; background = GradientDrawable().apply { shape = 1; setColor(Color.GREEN); setStroke(2, -1) } }
                playerView.addView(dot)
                val nameLabel = TextView(context).apply { text = p.name; setTextColor(-1); textSize = 10f; setShadowLayer(2f, 1f, 1f, Color.BLACK); layoutParams = FrameLayout.LayoutParams(-2, -2).apply { topMargin = 26; gravity = 1 } }
                playerView.addView(nameLabel)
                playerView.x = rect.left + (p.x!! / currentMapWidth * rect.width()).toFloat() - 12f
                playerView.y = rect.top + (p.y!! / currentMapHeight * rect.height()).toFloat() - 12f
                b.playersContainer.addView(playerView)
            }
        }
    }

    private fun checkPermissionsAndStartServices() {
        val perms = mutableListOf(Manifest.permission.ACCESS_FINE_LOCATION)
        if (Build.VERSION.SDK_INT >= 31) { perms.add(Manifest.permission.BLUETOOTH_SCAN); perms.add(Manifest.permission.BLUETOOTH_CONNECT) }
        if (perms.any { ContextCompat.checkSelfPermission(requireContext(), it) != 0 }) { permissionsLauncher.launch(perms.toTypedArray()) } 
        else { startLocationUpdates(); startBleScan() }
    }

    @SuppressLint("MissingPermission")
    private fun startLocationUpdates() {
        try { fusedLocationClient.requestLocationUpdates(LocationRequest.Builder(100, 10000).build(), locationCallback, Looper.getMainLooper()) } catch (e: Exception) { }
    }

    override fun onPause() { super.onPause() ; rosterRefreshJob?.cancel(); bleStatusJob?.cancel(); apiScanJob?.cancel() }
    override fun onDestroyView() { super.onDestroyView() ; _binding = null }
}