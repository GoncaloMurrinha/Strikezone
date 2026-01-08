package com.example.myapplication

import android.os.Bundle
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.os.bundleOf
import androidx.core.view.isVisible
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import com.example.myapplication.databinding.FragmentFirstBinding
import com.journeyapps.barcodescanner.ScanContract
import com.journeyapps.barcodescanner.ScanOptions
import kotlinx.coroutines.launch
import java.lang.Exception

class FirstFragment : Fragment() {

    private var _binding: FragmentFirstBinding? = null
    private val binding get() = _binding!!

    private val qrCodeScannerLauncher = registerForActivityResult(ScanContract()) { result ->
        if (result.contents != null) {
            binding.editTextCode.setText(result.contents)
            validateCode(result.contents)
        }
    }

    override fun onCreateView(
        inflater: LayoutInflater, container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentFirstBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        binding.buttonValidate.setOnClickListener {
            binding.errorText.text = "" // Clear previous errors
            val code = binding.editTextCode.text.toString().trim()
            if (code.isNotEmpty()) {
                validateCode(code)
            } else {
                binding.errorText.text = "Please enter a code"
            }
        }

        binding.buttonScanQr.setOnClickListener {
            val options = ScanOptions()
            options.setDesiredBarcodeFormats(ScanOptions.QR_CODE)
            options.setPrompt("Scan a QR code")
            options.setCameraId(0)  // Use a specific camera of the device
            options.setBeepEnabled(true)
            options.setBarcodeImageEnabled(true)
            qrCodeScannerLauncher.launch(options)
        }
    }

    private fun validateCode(code: String) {
        binding.validationLoader.isVisible = true
        viewLifecycleOwner.lifecycleScope.launch {
            try {
                val response = RetrofitInstance.api.validateCode(code)
                if (response.isSuccessful && response.body() != null) {
                    val validationResponse = response.body()!!
                    Log.d("FirstFragment", "Validation Response: $validationResponse")

                    if (validationResponse.status?.contains("ok", ignoreCase = true) == true) {
                        if (validationResponse.team != null && validationResponse.match_id != null && validationResponse.token != null) {
                            val bundle = bundleOf(
                                "team" to validationResponse.team,
                                "match_id" to validationResponse.match_id,
                                "token" to validationResponse.token
                            )
                            findNavController().navigate(R.id.action_FirstFragment_to_PlayerNameFragment, bundle)
                        } else {
                            findNavController().navigate(R.id.action_FirstFragment_to_CoordinatesFragment)
                        }
                    } else {
                        binding.errorText.text = "Invalid response from server"
                    }
                } else {
                    binding.errorText.text = "Validation failed: ${response.message()}"
                }
            } catch (e: Exception) {
                Log.e("FirstFragment", "Validation error", e)
                binding.errorText.text = "Error: ${e.message}"
            } finally {
                binding.validationLoader.isVisible = false
            }
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}