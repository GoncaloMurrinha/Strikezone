package com.example.myapplication

import android.Manifest
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothManager
import android.bluetooth.le.ScanCallback
import android.bluetooth.le.ScanResult
import android.bluetooth.le.ScanSettings
import android.content.Context
import android.content.Intent
import android.content.pm.ActivityInfo
import android.content.pm.PackageManager
import android.graphics.RectF
import android.os.Build
import android.os.Bundle
import android.os.Looper
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import androidx.core.view.isVisible
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.navArgs
import com.bumptech.glide.Glide
import com.example.myapplication.databinding.FragmentGameBinding
import com.google.android.gms.location.*
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import java.lang.Exception
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
        val manager = requireActivity().getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager
        manager?.adapter
    }

    private val uuidFloor0 = "76543212-1234-5678-1234-56789abcdef0"
    private val uuidFloor1 = "12345678-1234-5678-1234-56789abcdef0"

    private var ble1Active = false
    private var ble2Active = false
    private var ble1Rssi = -100
    private var ble2Rssi = -100
    private var ble1LastSeen = 0L
    private var ble2LastSeen = 0L
    private var currentFloor = "--"
    private var displayedFloor = ""
    private var isMapLoading = false

    private var currentMapWidth: Double = 0.0
    private var currentMapHeight: Double = 0.0
    private var currentBeacons: List<BeaconInfo> = emptyList()

    private val permissionsLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { startLocationUpdates(); startBleScan() }

    private val bluetoothEnableLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        if (bluetoothAdapter?.isEnabled == true) {
            startBleScan()
        }
    }

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentGameBinding.inflate(inflater, container, false)
        fusedLocationClient = LocationServices.getFusedLocationProviderClient(requireActivity())
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        setupSidePanel()

        locationCallback = object : LocationCallback() {
            override fun onLocationResult(locationResult: LocationResult) {
                if (_binding == null) return
                locationResult.lastLocation?.let {
                    binding.coordinatesTextView.text = "Lat: %.6f\nLon: %.6f".format(it.latitude, it.longitude)
                }
            }
        }
    }

    private fun setupSidePanel() {
        binding.teamNameTextView.text = if (args.team.equals("A", ignoreCase = true)) "Terrorista" else "Contra Terrorista"
    }

    private fun loadMap(floor: Int) {
        if (_binding == null || isMapLoading) return
        isMapLoading = true
        binding.mapLoader.isVisible = true

        lifecycleScope.launch {
            try {
                val response = RetrofitInstance.api.getMaps(args.matchId)
                if (response.isSuccessful && response.body()?.ok == true) {
                    val mapForFloor = response.body()?.maps?.firstOrNull { it.floor == floor }
                    if (_binding != null && mapForFloor != null) {
                        currentMapWidth = mapForFloor.width ?: 0.0
                        currentMapHeight = mapForFloor.height ?: 0.0
                        currentBeacons = mapForFloor.beacons ?: emptyList()

                        val url = mapForFloor.mapUrl.replace("http://central-app.local", RetrofitInstance.BASE_URL.removeSuffix("/"))
                        Glide.with(this@GameFragment).load(url).into(binding.mapImageView)
                        displayedFloor = floor.toString()
                    }
                }
            } catch (e: Exception) { Log.e("GameFragment", "Map error", e) }
            finally {
                isMapLoading = false
                _binding?.mapLoader?.isVisible = false
            }
        }
    }

    private val scanCallback = object : ScanCallback() {
        override fun onScanResult(callbackType: Int, result: ScanResult) {
            if (ActivityCompat.checkSelfPermission(requireContext(), Manifest.permission.BLUETOOTH_SCAN) != PackageManager.PERMISSION_GRANTED) {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) return
            }
            
            val deviceName = result.device.name ?: result.scanRecord?.deviceName
            val uuids = result.scanRecord?.serviceUuids?.map { it.uuid.toString().lowercase() } ?: emptyList()
            val now = System.currentTimeMillis()
            val rssi = result.rssi

            if ((deviceName != null && deviceName.endsWith("B0")) || uuids.contains(uuidFloor0.lowercase())) {
                ble1Active = true
                ble1LastSeen = now
                ble1Rssi = rssi
                if (rssi > -50) currentFloor = "0"
            }
            else if ((deviceName != null && deviceName.endsWith("B1")) || uuids.contains(uuidFloor1.lowercase())) {
                ble2Active = true
                ble2LastSeen = now
                ble2Rssi = rssi
                if (rssi > -50) currentFloor = "1"
            }
        }
    }

    private fun updateStatusUI() {
        if (_binding == null) return
        val now = System.currentTimeMillis()

        if (now - ble1LastSeen > 8000) { ble1Active = false; ble1Rssi = -100 }
        if (now - ble2LastSeen > 8000) { ble2Active = false; ble2Rssi = -100 }

        val status1 = if (ble1Active) "${ble1Rssi}dBm" else "--"
        val status2 = if (ble2Active) "${ble2Rssi}dBm" else "--"

        binding.bleStatusTextView.text = "B0: $status1 | B1: $status2"
        binding.floorTextView.text = "Andar: $currentFloor"

        if (currentFloor != "--" && currentFloor != displayedFloor && !isMapLoading) {
            loadMap(currentFloor.toInt())
        }
        
        updateUserDotPosition()
    }

    private fun updateUserDotPosition() {
        if (_binding == null || currentMapWidth <= 0 || currentMapHeight <= 0) {
            binding.userDot.isVisible = false
            return
        }

        val activeBeaconsOnMap = mutableListOf<Pair<BeaconInfo, Int>>()
        if (ble1Active) {
            currentBeacons.find { it.uuid.equals(uuidFloor0, ignoreCase = true) }?.let { activeBeaconsOnMap.add(it to ble1Rssi) }
        }
        if (ble2Active) {
            currentBeacons.find { it.uuid.equals(uuidFloor1, ignoreCase = true) }?.let { activeBeaconsOnMap.add(it to ble2Rssi) }
        }

        if (activeBeaconsOnMap.isEmpty()) {
            binding.userDot.isVisible = false
            return
        }

        var totalWeight = 0.0
        var weightedX = 0.0
        var weightedY = 0.0

        activeBeaconsOnMap.forEach { (beacon, rssi) ->
            val weight = (rssi + 100).toDouble().pow(2) 
            weightedX += beacon.x * weight
            weightedY += beacon.y * weight
            totalWeight += weight
        }

        val posX = weightedX / totalWeight
        val posY = weightedY / totalWeight

        val imageRect = getImageBounds(binding.mapImageView)
        if (imageRect.width() > 0) {
            val pixelX = imageRect.left + (posX / currentMapWidth * imageRect.width())
            val pixelY = imageRect.top + (posY / currentMapHeight * imageRect.height())

            binding.userDot.x = pixelX.toFloat() - (binding.userDot.width / 2)
            binding.userDot.y = pixelY.toFloat() - (binding.userDot.height / 2)
            binding.userDot.isVisible = true
        }
    }

    private fun getImageBounds(imageView: android.widget.ImageView): RectF {
        val drawable = imageView.drawable ?: return RectF()
        val values = FloatArray(9)
        imageView.imageMatrix.getValues(values)
        val left = values[android.graphics.Matrix.MTRANS_X]
        val top = values[android.graphics.Matrix.MTRANS_Y]
        val scaleX = values[android.graphics.Matrix.MSCALE_X]
        val scaleY = values[android.graphics.Matrix.MSCALE_Y]
        return RectF(left, top, left + (drawable.intrinsicWidth * scaleX), top + (drawable.intrinsicHeight * scaleY))
    }

    private suspend fun sendScanToServer() {
        if (!ble1Active && !ble2Active) return
        val readings = mutableListOf<ScanReading>()
        if (ble1Active) readings.add(ScanReading(uuidFloor0, 0, 0, ble1Rssi))
        if (ble2Active) readings.add(ScanReading(uuidFloor1, 0, 0, ble2Rssi))

        try {
            val request = ScanRequest(
                match_id = args.matchId,
                team_id = if (args.team == "A") 1 else 2,
                player_id = args.playerId,
                arena_id = 1,
                last_floor = if (currentFloor == "--") 0 else currentFloor.toInt(),
                readings = readings
            )
            RetrofitInstance.api.sendScan("Bearer ${args.token}", request)
        } catch (e: Exception) { Log.e("GameFragment", "Send scan error", e) }
    }

    private fun startLocationUpdates() {
        val request = LocationRequest.Builder(Priority.PRIORITY_HIGH_ACCURACY, 10000).build()
        try { 
            if (ActivityCompat.checkSelfPermission(requireContext(), Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED) {
                fusedLocationClient.requestLocationUpdates(request, locationCallback, Looper.getMainLooper()) 
            }
        } catch (e: SecurityException) { Log.e("GameFragment", "Location error", e) }
    }

    private fun startBleScan() {
        if (bluetoothAdapter == null) return
        if (bluetoothAdapter?.isEnabled == false) {
            val enableBtIntent = Intent(BluetoothAdapter.ACTION_REQUEST_ENABLE)
            bluetoothEnableLauncher.launch(enableBtIntent)
            return
        }
        val scanner = bluetoothAdapter?.bluetoothLeScanner ?: return
        val settings = ScanSettings.Builder().setScanMode(ScanSettings.SCAN_MODE_LOW_LATENCY).build()
        try { 
            if (ActivityCompat.checkSelfPermission(requireContext(), Manifest.permission.BLUETOOTH_SCAN) == PackageManager.PERMISSION_GRANTED || Build.VERSION.SDK_INT < Build.VERSION_CODES.S) {
                scanner.startScan(null, settings, scanCallback) 
            }
        } catch(e: SecurityException) { Log.e("GameFragment", "Scan error", e) }
    }

    override fun onResume() {
        super.onResume()
        requireActivity().requestedOrientation = ActivityInfo.SCREEN_ORIENTATION_LANDSCAPE
        checkPermissionsAndStartServices()
        if (displayedFloor == "") loadMap(0)

        rosterRefreshJob = lifecycleScope.launch {
            while(isActive) { loadTeamRoster(); delay(8000) }
        }
        bleStatusJob = lifecycleScope.launch {
            while(isActive) {
                activity?.runOnUiThread { updateStatusUI() }
                delay(1000)
            }
        }
        apiScanJob = lifecycleScope.launch {
            while(isActive) { 
                sendScanToServer()
                delay(1000)
            }
        }
    }

    private suspend fun loadTeamRoster() {
        if (_binding == null) return
        try {
            val response = RetrofitInstance.api.getTeamRoster("Bearer ${args.token}", args.matchId, args.team)
            if (_binding != null && response.isSuccessful) {
                binding.playerListContainer.removeAllViews()
                response.body()?.players?.forEach { player ->
                    val tv = TextView(requireContext()).apply {
                        text = "- ${player.name}"; setTextColor(ContextCompat.getColor(context, R.color.text_hint)); textSize = 16f
                    }
                    binding.playerListContainer.addView(tv)
                }
            }
        } catch (e: Exception) { }
    }

    private fun checkPermissionsAndStartServices() {
        val perms = mutableListOf(Manifest.permission.ACCESS_FINE_LOCATION)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            perms.add(Manifest.permission.BLUETOOTH_SCAN)
            perms.add(Manifest.permission.BLUETOOTH_CONNECT)
        }
        if (perms.any { ContextCompat.checkSelfPermission(requireContext(), it) != PackageManager.PERMISSION_GRANTED }) {
            permissionsLauncher.launch(perms.toTypedArray())
        } else {
            startLocationUpdates(); startBleScan()
        }
    }

    override fun onPause() {
        super.onPause()
        requireActivity().requestedOrientation = ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED
        fusedLocationClient.removeLocationUpdates(locationCallback)
        try { 
            if (ActivityCompat.checkSelfPermission(requireContext(), Manifest.permission.BLUETOOTH_SCAN) == PackageManager.PERMISSION_GRANTED || Build.VERSION.SDK_INT < Build.VERSION_CODES.S) {
                bluetoothAdapter?.bluetoothLeScanner?.stopScan(scanCallback) 
            }
        } catch (e: Exception) { }
        rosterRefreshJob?.cancel(); bleStatusJob?.cancel(); apiScanJob?.cancel()
    }

    override fun onDestroyView() { super.onDestroyView() ; _binding = null }
}