package com.example.myapplication

import android.os.Bundle
import android.util.Log
import androidx.fragment.app.Fragment
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.core.os.bundleOf
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import androidx.navigation.fragment.navArgs
import com.example.myapplication.databinding.FragmentPlayerNameBinding
import kotlinx.coroutines.launch
import java.lang.Exception

class PlayerNameFragment : Fragment() {

    private var _binding: FragmentPlayerNameBinding? = null
    private val binding get() = _binding!!

    private val args: PlayerNameFragmentArgs by navArgs()

    override fun onCreateView(
        inflater: LayoutInflater, container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentPlayerNameBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        binding.buttonSubmitName.setOnClickListener {
            binding.errorText.text = "" // Clear previous errors
            val playerName = binding.editTextPlayerName.text.toString().trim()
            if (playerName.isNotEmpty()) {
                registerPlayer(playerName, args.team, args.matchId, args.token)
            } else {
                binding.errorText.text = "Please enter a name"
            }
        }
    }

    private fun registerPlayer(name: String, team: String, matchId: Int, token: String) {
        (activity as? LoaderProvider)?.showLoader()
        viewLifecycleOwner.lifecycleScope.launch {
            try {
                val request = RegisterPlayerRequest(
                    match_id = matchId,
                    side = team,
                    display_name = name
                )

                val response = RetrofitInstance.api.registerPlayer("Bearer $token", request)
                val responseBody = response.body()

                Log.d("PlayerNameFragment", "Registration Response: $responseBody")

                if (response.isSuccessful && responseBody?.ok == true && responseBody.player_id != null) {
                    val bundle = bundleOf(
                        "team" to team,
                        "match_id" to matchId,
                        "token" to token,
                        "player_id" to responseBody.player_id
                    )
                    findNavController().navigate(R.id.action_PlayerNameFragment_to_GameFragment, bundle)
                } else {
                    binding.errorText.text = "Failed to register player: ${response.message()}"
                    (activity as? LoaderProvider)?.hideLoader()
                }
            } catch (e: Exception) {
                Log.e("PlayerNameFragment", "Registration error", e)
                binding.errorText.text = "Error: ${e.message}"
                (activity as? LoaderProvider)?.hideLoader()
            }
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}