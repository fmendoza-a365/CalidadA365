package com.qa365.mobile

import android.media.MediaPlayer
import android.media.PlaybackParams
import android.os.Build
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Pause
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.Speed
import androidx.compose.material.icons.filled.FastForward
import androidx.compose.material.icons.filled.FastRewind
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
fun AudioPlayer(url: String, token: String?, title: String = "Reproductor de Audio", subtitle: String? = null) {
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
                color = MaterialTheme.colorScheme.outline.copy(alpha = 0.2f),
                shape = RoundedCornerShape(16.dp)
            ),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text(
                text = title,
                fontSize = 14.sp,
                fontWeight = FontWeight.ExtraBold,
                color = MaterialTheme.colorScheme.onSurface
            )
            if (!subtitle.isNullOrBlank()) {
                Spacer(modifier = Modifier.height(3.dp))
                Text(
                    text = subtitle,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.56f)
                )
            }
            Spacer(modifier = Modifier.height(14.dp))

            // Waveform visualizer preview
            val waveBarHeights = listOf(
                10, 16, 12, 20, 26, 14, 10, 18, 22, 30, 
                24, 16, 12, 20, 28, 22, 14, 18, 26, 32, 
                22, 14, 10, 16, 20, 12, 8, 14, 18, 12
            )
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(36.dp)
                    .padding(vertical = 2.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                val progress = if (totalDuration > 0) currentPosition.toFloat() / totalDuration else 0f
                waveBarHeights.forEachIndexed { index, heightDp ->
                    val barProgress = index.toFloat() / waveBarHeights.size
                    val color = if (progress >= barProgress) {
                        MaterialTheme.colorScheme.primary
                    } else {
                        MaterialTheme.colorScheme.onSurface.copy(alpha = 0.15f)
                    }
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .padding(horizontal = 1.5.dp)
                            .height(heightDp.dp)
                            .clip(RoundedCornerShape(1.dp))
                            .background(color)
                    )
                }
            }

            Spacer(modifier = Modifier.height(6.dp))

            // Seeker / Slider
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
                modifier = Modifier.fillMaxWidth().height(16.dp),
                colors = SliderDefaults.colors(
                    activeTrackColor = MaterialTheme.colorScheme.primary,
                    inactiveTrackColor = MaterialTheme.colorScheme.outline.copy(alpha = 0.15f),
                    thumbColor = MaterialTheme.colorScheme.primary
                )
            )

            // Time markers: Current / Total
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 4.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = formatTime(currentPosition),
                    fontSize = 11.sp,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                )
                Text(
                    text = formatTime(totalDuration),
                    fontSize = 11.sp,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                )
            }

            Spacer(modifier = Modifier.height(16.dp))

            // Playback controls row
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Playback speed chips on the left (smaller size, rounded)
                Row(
                    horizontalArrangement = Arrangement.spacedBy(4.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    listOf(1.0f, 1.5f, 2.0f).forEach { speed ->
                        val isSelected = playbackSpeed == speed
                        val textBg = if (isSelected) MaterialTheme.colorScheme.primary.copy(alpha = 0.15f) else MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.4f)
                        val textColor = if (isSelected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f)
                        
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(8.dp))
                                .background(textBg)
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
                                .padding(horizontal = 8.dp, vertical = 6.dp)
                        ) {
                            Text(
                                text = "${speed}x",
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Bold,
                                color = textColor
                            )
                        }
                    }
                }

                // Center controls: Rewind, Play/Pause, Forward
                Row(
                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    IconButton(
                        onClick = {
                            mediaPlayer?.let { player ->
                                val target = maxOf(0, player.currentPosition - 10000)
                                player.seekTo(target)
                                currentPosition = target
                            }
                        },
                        enabled = mediaPlayer != null,
                        modifier = Modifier.size(36.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.FastRewind,
                            contentDescription = "Retroceder 10s",
                            tint = if (mediaPlayer != null) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurface.copy(alpha = 0.3f),
                            modifier = Modifier.size(20.dp)
                        )
                    }

                    // Play/Pause circular button
                    Box(
                        modifier = Modifier
                            .size(48.dp)
                            .clip(CircleShape)
                            .background(MaterialTheme.colorScheme.primary)
                            .clickable {
                                if (isPlaying) {
                                    mediaPlayer?.pause()
                                    isPlaying = false
                                } else {
                                    mediaPlayer?.let { player ->
                                        player.start()
                                        isPlaying = true
                                        return@clickable
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
                        contentAlignment = Alignment.Center
                    ) {
                        if (isLoading) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(22.dp),
                                color = MaterialTheme.colorScheme.onPrimary,
                                strokeWidth = 2.5.dp
                            )
                        } else {
                            Icon(
                                imageVector = if (isPlaying) Icons.Default.Pause else Icons.Default.PlayArrow,
                                contentDescription = null,
                                tint = MaterialTheme.colorScheme.onPrimary,
                                modifier = Modifier.size(24.dp)
                            )
                        }
                    }

                    IconButton(
                        onClick = {
                            mediaPlayer?.let { player ->
                                val target = minOf(totalDuration, player.currentPosition + 10000)
                                player.seekTo(target)
                                currentPosition = target
                            }
                        },
                        enabled = mediaPlayer != null,
                        modifier = Modifier.size(36.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.FastForward,
                            contentDescription = "Adelantar 10s",
                            tint = if (mediaPlayer != null) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurface.copy(alpha = 0.3f),
                            modifier = Modifier.size(20.dp)
                        )
                    }
                }
                
                // Speed label or icon placeholder to balance row
                Spacer(modifier = Modifier.width(76.dp))
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
