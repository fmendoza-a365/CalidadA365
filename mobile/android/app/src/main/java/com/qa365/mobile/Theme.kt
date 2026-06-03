package com.qa365.mobile

import android.app.Activity
import android.os.Build
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.dynamicDarkColorScheme
import androidx.compose.material3.dynamicLightColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.core.view.WindowCompat

val Green = Color(0xFF12B886)
val Blue = Color(0xFF3B82F6)
val Amber = Color(0xFFF59E0B)
val Rose = Color(0xFFF43F5E)
val Violet = Color(0xFF7C3AED)
val Cyan = Color(0xFF06B6D4)

private val DarkColorScheme = darkColorScheme(
    primary = Color(0xFF6366F1),       // Indigo 500
    secondary = Color(0xFF4F46E5),     // Indigo 600
    tertiary = Amber,
    background = Color(0xFF0A0A0A),    // Neutral 950
    surface = Color(0xFF171717),       // Neutral 900
    surfaceVariant = Color(0xFF262626),// Neutral 800
    onPrimary = Color.White,
    onSecondary = Color.White,
    onTertiary = Color.White,
    onBackground = Color(0xFFF5F5F5),  // Neutral 100
    onSurface = Color(0xFFF5F5F5),     // Neutral 100
    onSurfaceVariant = Color(0xFFA3A3A3), // Neutral 400
    error = Color(0xFFEF4444),         // Red 500
    outline = Color(0xFF404040),       // Neutral 700
)

private val LightColorScheme = lightColorScheme(
    primary = Color(0xFF6366F1),       // Indigo 500
    secondary = Color(0xFF4F46E5),     // Indigo 600
    tertiary = Amber,
    background = Color(0xFFFAF5F5),    // Web background (Light gray)
    surface = Color.White,
    surfaceVariant = Color(0xFFE5E5E5),// Neutral 200
    onPrimary = Color.White,
    onSecondary = Color.White,
    onTertiary = Color.White,
    onBackground = Color(0xFF171717),  // Neutral 900
    onSurface = Color(0xFF171717),     // Neutral 900
    onSurfaceVariant = Color(0xFF525252), // Neutral 600
    error = Color(0xFFDC2626),         // Red 600
    outline = Color(0xFFD4D4D4),       // Neutral 300
)

@Composable
fun QA365Theme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    val colorScheme = when {
        darkTheme -> DarkColorScheme
        else -> LightColorScheme
    }
    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as Activity).window
            window.statusBarColor = colorScheme.background.toArgb()
            window.navigationBarColor = colorScheme.surface.toArgb()
            WindowCompat.getInsetsController(window, view).isAppearanceLightStatusBars = !darkTheme
        }
    }

    MaterialTheme(
        colorScheme = colorScheme,
        content = content
    )
}
