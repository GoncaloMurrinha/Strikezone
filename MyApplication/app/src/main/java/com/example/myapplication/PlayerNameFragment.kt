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
            binding.errorText.text = "" 
            val playerName = binding.editTextPlayerName.text.toString().trim()
            if (playerName.isNotEmpty()) {
                registerOrLoginPlayer(playerName, args.team, args.matchId, args.token)
            } else {
                binding.errorText.text = "Por favor, insira um nome"
            }
        }
    }

    private fun registerOrLoginPlayer(name: String, team: String, matchId: Int, token: String) {
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

                if (response.isSuccessful && responseBody?.ok == true && responseBody.player_id != null) {
                    // Registo novo com sucesso
                    navigateToGame(team, matchId, token, responseBody.player_id)
                } else {
                    // Se falhar (provavelmente porque o nome já existe), tentamos recuperar o ID do Roster
                    val rosterResponse = RetrofitInstance.api.getTeamRoster("Bearer $token", matchId, team)
                    if (rosterResponse.isSuccessful) {
                        val existingPlayer = rosterResponse.body()?.players?.find { it.name.equals(name, ignoreCase = true) }
                        if (existingPlayer != null && existingPlayer.id != null) {
                            // O jogador já existia, vamos "entrar" com o ID dele
                            navigateToGame(team, matchId, token, existingPlayer.id)
                        } else {
                            val errorMsg = if (!response.isSuccessful) response.message() else "Nome já em uso por outra equipa."
                            binding.errorText.text = "Erro: $errorMsg"
                            (activity as? LoaderProvider)?.hideLoader()
                        }
                    } else {
                        binding.errorText.text = "Falha no registo e impossível recuperar jogador existente."
                        (activity as? LoaderProvider)?.hideLoader()
                    }
                }
            } catch (e: Exception) {
                Log.e("PlayerNameFragment", "Error", e)
                binding.errorText.text = "Erro de rede: ${e.message}"
                (activity as? LoaderProvider)?.hideLoader()
            }
        }
    }

    private fun navigateToGame(team: String, matchId: Int, token: String, playerId: Int) {
        val bundle = bundleOf(
            "team" to team,
            "match_id" to matchId,
            "token" to token,
            "player_id" to playerId
        )
        findNavController().navigate(R.id.action_PlayerNameFragment_to_GameFragment, bundle)
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}