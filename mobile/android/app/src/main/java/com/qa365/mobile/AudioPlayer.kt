package com.qa365.mobile

import android.media.MediaPlayer
import android.media.PlaybackParams
import android.os.Build
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Pause
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.Speed
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

@Composable
fun AudioPlayer(url: String, token: String?) {
    var isPlaying by remember { mutableStateOf(false) }
    var isLoading by remember { mutableStateOf(false) }
    var mediaPlayer by remember { mutableStateOf<MediaPlayer?>(null) }
    var errorMessage by remember { mutableStateOf<String?>(null) }

    var currentPosition by remember { mutableIntStateOf(0) }
    var totalDuration by remember { mutableIntStateOf(0) }
    var playbackSpeed by remember { mutableFloatStateOf(1.0f) }
    var isSliderDragging by remember { mutableStateOf(false) }

    val context = LocalContext.current
    val coroutineScope = rememberCoroutineScope()

    // Clean up player on dispose
    DisposableEffect(Unit) {
        onDispose {
            mediaPlayer?.stop()
            mediaPlayer?.release()
        }
    }

    // Coroutine to poll current position
    LaunchedEffect(isPlaying) {
        if (isPlaying) {
            while (isPlaying) {
                mediaPlayer?.let { player ->
                    if (player.isPlaying && !isSliderDragging) {
                        currentPosition = player.currentPosition
                    }
                }
                delay(250)
            }
        }
    }

    // Set speed on player when it changes
    LaunchedEffect(playbackSpeed, mediaPlayer) {
        mediaPlayer?.let { player ->
            if (player.isPlaying || isPlaying) {
                try {
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                        player.playbackParams = player.playbackParams.setSpeed(playbackSpeed)
                    }
                } catch (e: Exception) {
                    // Fallback if not supported
                }
            }
        }
    }

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 8.dp)
            .border(
                width = 1.dp,
                color = MaterialTheme.colorScheme.outline,
                shape = RoundedCornerShape(12.dp)
            ),
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text(
                text = "Reproductor de Audio",
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface
            )
            Spacer(modifier = Modifier.height(12.dp))

            // Seeker / Slider
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically
            ) {
                val progress = if (totalDuration > 0) currentPosition.toFloat() / totalDuration else 0f
                Slider(
                    value = progress,
                    onValueChange = { newValue ->
                        isSliderDragging = true
                        currentPosition = (newValue * totalDuration).toInt()
                    },
                    onValueChangeFinished = {
                        isSliderDragging = false
                        mediaPlayer?.let { player ->
                            player.seekTo(currentPosition)
                        }
                    },
                    modifier = Modifier.weight(1f),
                    colors = SliderDefaults.colors(
                        activeTrackColor = MaterialTheme.colorScheme.primary,
                        inactiveTrackColor = MaterialTheme.colorScheme.surfaceVariant,
                        thumbColor = MaterialTheme.colorScheme.primary
                    )
                )
            }

            // Time markers: Current / Total
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 4.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "${formatTime(currentPosition)} / ${formatTime(totalDuration)}",
                    fontSize = 12.sp,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f)
                )
            }

            Spacer(modifier = Modifier.height(16.dp))

            // Playback controls row
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Play / Pause Button
                Button(
                    onClick = {
                        if (isPlaying) {
                            mediaPlayer?.pause()
                            isPlaying = false
                        } else {
                            mediaPlayer?.let { player ->
                                player.start()
                                isPlaying = true
                                return@Button
                            }

                            // Initialize new player
                            isLoading = true
                            errorMessage = null
                            coroutineScope.launch {
                                try {
                                    val player = MediaPlayer()
                                    val headers = mutableMapOf<String, String>()
                                    if (!token.isNullOrEmpty()) {
                                        headers["Authorization"] = "Bearer $token"
                                    }
                                    player.setDataSource(context, android.net.Uri.parse(url), headers)
                                    player.setOnPreparedListener { preparedPlayer ->
                                        isLoading = false
                                        totalDuration = preparedPlayer.duration
                                        
                                        // Apply speed params
                                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                                            try {
                                                preparedPlayer.playbackParams = preparedPlayer.playbackParams.setSpeed(playbackSpeed)
                                            } catch (e: Exception) {}
                                        }
                                        preparedPlayer.start()
                                        isPlaying = true
                                    }
                                    player.setOnCompletionListener { completedPlayer ->
                                        isPlaying = false
                                        currentPosition = 0
                                    }
                                    player.setOnErrorListener { _, _, _ ->
                                        isLoading = false
                                        isPlaying = false
                                        errorMessage = "Error al reproducir el audio."
                                        true
                                    }
                                    mediaPlayer = player
                                    player.prepareAsync()
                                } catch (e: Exception) {
                                    isLoading = false
                                    errorMessage = "Error: ${e.message}"
                                }
                            }
                        }
                    },
                    modifier = Modifier.height(44.dp),
                    shape = RoundedCornerShape(8.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.primary)
                ) {
                    if (isLoading) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(20.dp),
                            color = MaterialTheme.colorScheme.onPrimary,
                            strokeWidth = 2.dp
                        )
                    } else {
                        Icon(
                            imageVector = if (isPlaying) Icons.Default.Pause else Icons.Default.PlayArrow,
                            contentDescription = null,
                            modifier = Modifier.size(18.dp)
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            text = if (isPlaying) "Pausar" else "Reproducir",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Bold
                        )
                    }
                }

                // Speed Selector Options
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.Speed,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f),
                        modifier = Modifier.size(16.dp)
                    )
                    Spacer(modifier = Modifier.width(2.dp))
                    
                    listOf(1.0f, 1.25f, 1.5f, 2.0f).forEach { speed ->
                        val isSelected = playbackSpeed == speed
                        val textColor = if (isSelected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f)
                        val textBg = if (isSelected) MaterialTheme.colorScheme.primary.copy(alpha = 0.1f) else androidx.compose.ui.graphics.Color.Transparent
                        val borderMod = if (isSelected) Modifier.border(width = 0.5.dp, color = MaterialTheme.colorScheme.primary.copy(alpha = 0.2f), shape = RoundedCornerShape(4.dp)) else Modifier
                        
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(4.dp))
                                .background(textBg)
                                .then(borderMod)
                                .clickable {
                                    playbackSpeed = speed
                                    mediaPlayer?.let { player ->
                                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                                            try {
                                                player.playbackParams = player.playbackParams.setSpeed(speed)
                                            } catch (e: Exception) {}
                                        }
                                    }
                                }
                                .padding(horizontal = 6.dp, vertical = 4.dp)
                        ) {
                            Text(
                                text = "${speed}x",
                                fontSize = 11.sp,
                                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                color = textColor
                            )
                        }
                    }
                }
            }

            if (errorMessage != null) {
                Spacer(modifier = Modifier.height(10.dp))
                Text(
                    text = errorMessage!!,
                    color = MaterialTheme.colorScheme.error,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Medium
                )
            }
        }
    }
}

private fun formatTime(millis: Int): String {
    val seconds = (millis / 1000) % 60
    val minutes = (millis / (1000 * 60)) % 60
    return String.format("%02d:%02d", minutes, seconds)
}
