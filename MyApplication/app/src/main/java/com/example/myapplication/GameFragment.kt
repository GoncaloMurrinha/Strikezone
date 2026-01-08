package com.example.myapplication

import android.Manifest
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothManager
import android.bluetooth.le.ScanCallback
import android.bluetooth.le.ScanFilter
import android.bluetooth.le.ScanResult
import android.bluetooth.le.ScanSettings
import android.content.Context
import android.content.pm.ActivityInfo
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.os.Looper
import android.os.ParcelUuid
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.activity.result.contract.ActivityResultContracts
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
import java.util.UUID

class GameFragment : Fragment() {

    private var _binding: FragmentGameBinding? = null
    private val binding get() = _binding!!

    private val args: GameFragmentArgs by navArgs()

    private lateinit var fusedLocationClient: FusedLocationProviderClient
    private lateinit var locationCallback: LocationCallback
    private var rosterRefreshJob: Job? = null
    private var bleStatusJob: Job? = null

    private val bluetoothAdapter: BluetoothAdapter? by lazy {
        val manager = requireActivity().getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager
        manager?.adapter
    }

    // UUIDs fornecidos para redundância
    private val uuidFloor0 = "76543212-1234-5678-1234-56789abcdef0"
    private val uuidFloor1 = "12345678-1234-5678-1234-56789abcdef0"

    private var ble1Active = false
    private var ble2Active = false
    private var ble1LastSeen = 0L
    private var ble2LastSeen = 0L
    private var currentFloor = "--"
    private var displayedFloor = ""
    private var isMapLoading = false

    private val permissionsLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { startLocationUpdates(); startBleScan() }

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

    private suspend fun loadTeamRoster() {
        if (_binding == null) return
        try {
            val response = RetrofitInstance.api.getTeamRoster("Bearer ${args.token}", args.matchId, args.team)
            if (_binding != null && response.isSuccessful && response.body()?.ok == true) {
                binding.playerListContainer.removeAllViews()
                response.body()?.players?.forEach { player ->
                    val tv = TextView(requireContext()).apply {
                        text = "- ${player.name}"
                        setTextColor(ContextCompat.getColor(context, R.color.text_hint))
                        textSize = 16f
                    }
                    binding.playerListContainer.addView(tv)
                }
            }
        } catch (e: Exception) { Log.e("GameFragment", "Roster error") }
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
                    if (_binding != null) {
                        if (mapForFloor != null) {
                            val url = mapForFloor.mapUrl.replace("http://central-app.local", RetrofitInstance.BASE_URL.removeSuffix("/"))
                            Glide.with(this@GameFragment).load(url).into(binding.mapImageView)
                        } else {
                            binding.mapImageView.setImageDrawable(null)
                        }
                        displayedFloor = floor.toString()
                    }
                }
            } catch (e: Exception) { Log.e("GameFragment", "Map error") }
            finally { 
                isMapLoading = false
                _binding?.mapLoader?.isVisible = false 
            }
        }
    }

    private val scanCallback = object : ScanCallback() {
        override fun onScanResult(callbackType: Int, result: ScanResult) {
            val deviceName = result.device.name ?: result.scanRecord?.deviceName
            val uuids = result.scanRecord?.serviceUuids?.map { it.uuid.toString().lowercase() } ?: emptyList()
            val now = System.currentTimeMillis()

            // Lógica para Andar 0 (B0)
            if ((deviceName != null && deviceName.endsWith("B0")) || uuids.contains(uuidFloor0.lowercase())) {
                ble1Active = true
                ble1LastSeen = now
                currentFloor = "0"
                Log.d("BLE_SCAN", "Detectado B0 (Andar 0)")
            } 
            // Lógica para Andar 1 (B1)
            else if ((deviceName != null && deviceName.endsWith("B1")) || uuids.contains(uuidFloor1.lowercase())) {
                ble2Active = true
                ble2LastSeen = now
                currentFloor = "1"
                Log.d("BLE_SCAN", "Detectado B1 (Andar 1)")
            }
        }
    }

    private fun updateStatusUI() {
        if (_binding == null) return
        val now = System.currentTimeMillis()
        
        if (now - ble1LastSeen > 8000) ble1Active = false
        if (now - ble2LastSeen > 8000) ble2Active = false
        
        binding.bleStatusTextView.text = "BLE 1: ${if (ble1Active) "OK" else "--"} | BLE 2: ${if (ble2Active) "OK" else "--"}"
        binding.floorTextView.text = "Andar: $currentFloor"

        if (currentFloor != "--" && currentFloor != displayedFloor && !isMapLoading) {
            loadMap(currentFloor.toInt())
        }
    }

    private fun startLocationUpdates() {
        val request = LocationRequest.Builder(Priority.PRIORITY_HIGH_ACCURACY, 10000).build()
        try { fusedLocationClient.requestLocationUpdates(request, locationCallback, Looper.getMainLooper()) } catch (e: SecurityException) { }
    }

    private fun startBleScan() {
        val scanner = bluetoothAdapter?.bluetoothLeScanner ?: return
        if (bluetoothAdapter?.isEnabled == false) return

        val settings = ScanSettings.Builder()
            .setScanMode(ScanSettings.SCAN_MODE_LOW_LATENCY)
            .build()

        try {
            scanner.startScan(null, settings, scanCallback)
            Log.d("BLE_SCAN", "Scan híbrido iniciado (Nome + UUID)")
        } catch(e: SecurityException) { }
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
                delay(3000)
            }
        }
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
        try { bluetoothAdapter?.bluetoothLeScanner?.stopScan(scanCallback) } catch (e: Exception) { }
        rosterRefreshJob?.cancel(); bleStatusJob?.cancel()
    }

    override fun onDestroyView() { super.onDestroyView() ; _binding = null }
}