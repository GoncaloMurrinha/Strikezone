package com.example.myapplication

import android.util.Log
import com.google.gson.GsonBuilder
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

object RetrofitInstance {
    
    /**
     * IMPORTANTE: Altera este IP para o IP atual do teu computador!
     * Podes ver o IP no terminal do teu PC com o comando 'ipconfig' (Windows) ou 'ifconfig' (Mac).
     */
    private const val SERVER_IP = "192.168.1.74" 
    private const val BASE_URL_STRING = "http://$SERVER_IP:8080/"

    val api: ApiService by lazy {
        val logger = HttpLoggingInterceptor.Logger { message -> Log.i("OkHttp", message) }
        val logging = HttpLoggingInterceptor(logger).apply {
            setLevel(HttpLoggingInterceptor.Level.BODY)
        }

        val client = OkHttpClient.Builder()
            .addInterceptor(logging)
            .connectTimeout(60, TimeUnit.SECONDS)
            .readTimeout(60, TimeUnit.SECONDS)
            .writeTimeout(60, TimeUnit.SECONDS)
            .retryOnConnectionFailure(true)
            .build()

        Retrofit.Builder()
            .baseUrl(BASE_URL_STRING)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create(GsonBuilder().setLenient().create()))
            .build()
            .create(ApiService::class.java)
    }

    val BASE_URL: String get() = BASE_URL_STRING
}