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
    
    // IP Fixo para evitar falhas de deteção
    private const val FIXED_IP = "192.168.100.165"
    private const val BASE_URL_STRING = "http://$FIXED_IP:8080/"

    val api: ApiService by lazy {
        val logger = HttpLoggingInterceptor.Logger { message -> Log.i("OkHttp", message) }
        val logging = HttpLoggingInterceptor(logger).apply {
            setLevel(HttpLoggingInterceptor.Level.BODY)
        }

        val client = OkHttpClient.Builder()
            .addInterceptor(logging)
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(15, TimeUnit.SECONDS)
            .writeTimeout(15, TimeUnit.SECONDS)
            // Força o uso do Wi-Fi se necessário, mas aqui apenas garantimos que o OkHttp não desista rápido
            .retryOnConnectionFailure(true)
            .build()

        Retrofit.Builder()
            .baseUrl(BASE_URL_STRING)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create(GsonBuilder().setLenient().create()))
            .build()
            .create(ApiService::class.java)
    }

    // Mantemos a propriedade BASE_URL para compatibilidade com o resto do código
    val BASE_URL: String get() = BASE_URL_STRING
}