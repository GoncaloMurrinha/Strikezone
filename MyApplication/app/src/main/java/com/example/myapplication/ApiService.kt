package com.example.myapplication

import com.google.gson.annotations.SerializedName
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.POST
import retrofit2.http.Query

// Step 1: Response from code validation
data class ValidationResponse(
    val status: String?,
    val team: String?,
    val match_id: Int?,
    val token: String?
)

// Step 2: Request and Response for player registration
data class RegisterPlayerRequest(
    val match_id: Int,
    val side: String,
    val display_name: String
)
data class RegisterPlayerResponse(
    val ok: Boolean?,
    val player_id: Int?
)

// Step 3: Request and Response for sending scans
data class ScanReading(val uuid: String, val major: Int, val minor: Int, val rssi: Int)
data class ScanRequest(
    val match_id: Int,
    val team_id: Int,
    val player_id: Int,
    val arena_id: Int,
    val last_floor: Int,
    val readings: List<ScanReading>
)
data class ScanResponse(val status: String)

// Step 4: Response for maps
data class MapInfo(
    val floor: Int,
    @SerializedName("map_url") val mapUrl: String
)
data class MapsResponse(
    val ok: Boolean,
    val maps: List<MapInfo>
)

// Step 5: Response for Team Roster (Corrected)
data class PlayerInfo(
    val name: String // Corrected from displayName
)
data class TeamRosterResponse(
    val ok: Boolean,
    val players: List<PlayerInfo> // Corrected from roster
)


interface ApiService {
    @GET("api/code/resolve")
    suspend fun validateCode(@Query("code") code: String): Response<ValidationResponse>

    @POST("api/match/register-player")
    suspend fun registerPlayer(
        @Header("Authorization") token: String,
        @Body request: RegisterPlayerRequest
    ): Response<RegisterPlayerResponse>

    @POST("api/scan")
    suspend fun sendScan(
        @Header("Authorization") token: String,
        @Body request: ScanRequest
    ): Response<ScanResponse>

    @GET("api/maps")
    suspend fun getMaps(@Query("match_id") matchId: Int): Response<MapsResponse>

    @GET("api/match/team-roster")
    suspend fun getTeamRoster(
        @Header("Authorization") token: String,
        @Query("match_id") matchId: Int,
        @Query("side") side: String
    ): Response<TeamRosterResponse>
}