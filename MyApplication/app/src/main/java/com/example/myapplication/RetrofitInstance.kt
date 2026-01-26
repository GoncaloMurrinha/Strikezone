package com.example.myapplication

import android.util.Log
import com.google.gson.GsonBuilder
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.net.NetworkInterface
import java.util.Collections
import java.util.concurrent.TimeUnit

object RetrofitInstance {
    
    private var cachedBaseUrl: String? = null

    private fun getLocalIpAddress(): String? {
        try {
            val interfaces = Collections.list(NetworkInterface.getNetworkInterfaces())
            for (intf in interfaces) {
                val addrs = Collections.list(intf.inetAddresses)
                for (addr in addrs) {
                    if (!addr.isLoopbackAddress) {
                        val sAddr = addr.hostAddress
                        if (sAddr != null && !sAddr.contains(":")) return sAddr
                    }
                }
            }
        } catch (e: Exception) {
            Log.e("RetrofitInstance", "Erro ao detetar IP", e)
        }
        return null
    }

    private fun getDynamicBaseUrl(): String {
        cachedBaseUrl?.let { return it }

        val localIp = getLocalIpAddress() ?: ""
        Log.d("RetrofitInstance", "IP do Telemóvel detetado pela primeira vez: $localIp")

        // Prioridade para o IP do servidor local conhecido se estivermos na mesma rede
        val url = when {
            localIp.startsWith("10.0.2") -> "http://10.0.2.2:8080/"
            localIp.startsWith("172.20.10") -> "http://172.20.10.3:8080/"
            localIp.startsWith("192.168.100") -> "http://192.168.100.165:8080/"
            else -> "http://192.168.100.165:8080/"
        }
        
        cachedBaseUrl = url
        return url
    }

    val BASE_URL: String get() = getDynamicBaseUrl()

    val api: ApiService by lazy {
        val logger = HttpLoggingInterceptor.Logger { message -> Log.i("OkHttp", message) }
        val logging = HttpLoggingInterceptor(logger).apply {
            setLevel(HttpLoggingInterceptor.Level.BODY)
        }

        val client = OkHttpClient.Builder()
            .addInterceptor(logging)
            .connectTimeout(30, TimeUnit.SECONDS) // Aumentado timeout
            .readTimeout(30, TimeUnit.SECONDS)
            .writeTimeout(30, TimeUnit.SECONDS)
            .build()

        Retrofit.Builder()
            .baseUrl(BASE_URL)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create(GsonBuilder().setLenient().create()))
            .build()
            .create(ApiService::class.java)
    }
}