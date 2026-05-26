package com.vocabapp.wordapp.viewmodel

import android.content.Context

class SettingsStore(context: Context) {
    private val prefs = context.getSharedPreferences("wordapp-settings", Context.MODE_PRIVATE)

    var newWordsPerDay: Int
        get() = prefs.getInt("new_words_per_day", 80)
        set(value) = prefs.edit().putInt("new_words_per_day", value.coerceIn(1, 100)).apply()

    var reviewLimit: Int
        get() = prefs.getInt("review_limit", 80)
        set(value) = prefs.edit().putInt("review_limit", value.coerceIn(1, 300)).apply()

    var apiBaseUrl: String
        get() = prefs.getString("api_base_url", "https://layer-city.com/wordapp/api") ?: "https://layer-city.com/wordapp/api"
        set(value) = prefs.edit().putString("api_base_url", value.trim().trimEnd('/')).apply()
}
