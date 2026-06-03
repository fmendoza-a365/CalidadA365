package com.qa365.mobile

import android.content.Context
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import org.json.JSONObject

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DetailScreen(
    type: String,
    data: JSONObject,
    token: String?,
    serverUrl: String,
    onBack: () -> Unit
) {
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(text = getDetailTitle(type)) },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.Default.ArrowBack, contentDescription = "Volver")
                    }
                }
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(16.dp)
                .verticalScroll(rememberScrollState())
        ) {
            when (type) {
                "evaluation" -> EvaluationDetail(data, token, serverUrl)
                "transcript" -> TranscriptDetail(data, token)
                "campaign" -> CampaignDetail(data)
                else -> Text("Detalle no soportado aún.")
            }
        }
    }
}

fun getDetailTitle(type: String): String {
    return when (type) {
        "evaluation" -> "Evaluación"
        "transcript" -> "Transcripción"
        "campaign" -> "Campaña"
        else -> "Detalle"
    }
}

@Composable
fun EvaluationDetail(evaluation: JSONObject, token: String?, serverUrl: String) {
    val score = evaluation.optDouble("score", -1.0)
    var isSubmitting by remember { mutableStateOf(false) }
    var comment by remember { mutableStateOf("") }
    var feedbackMessage by remember { mutableStateOf<String?>(null) }
    val coroutineScope = rememberCoroutineScope()
    
    SectionHeader("Resultado de calidad", evaluation.optString("campaign", "Sin campaña"))
    
    Card(modifier = Modifier.fillMaxWidth().padding(vertical = 8.dp)) {
        Column(modifier = Modifier.padding(16.dp)) {
            ProgressLine("Nota General", if (score < 0) 0.0 else score, getScoreColor(score))
            Spacer(modifier = Modifier.height(16.dp))
            InfoRow("Estado", evaluation.optString("status_label", "Sin estado"))
            InfoRow("Asesor", evaluation.optString("agent", "Sin dato"))
            InfoRow("Monitor", evaluation.optString("evaluator", "Sin dato"))
        }
    }

    Spacer(modifier = Modifier.height(16.dp))
    SectionHeader("Resumen")
    Text(text = evaluation.optString("summary", "Sin resumen disponible."), style = MaterialTheme.typography.bodyMedium)

    val response = evaluation.optJSONObject("feedback_response") ?: JSONObject()
    if (!response.optBoolean("responded") && evaluation.optString("status") == "published_to_agent") {
        Spacer(modifier = Modifier.height(24.dp))
        SectionHeader("Responder Feedback")
        Text("La respuesta se guarda en el sistema y se refleja también en la web.")
        Spacer(modifier = Modifier.height(8.dp))
        OutlinedTextField(
            value = comment,
            onValueChange = { comment = it },
            label = { Text("Comentario o motivo de disputa") },
            modifier = Modifier.fillMaxWidth(),
            minLines = 3
        )
        Spacer(modifier = Modifier.height(8.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Button(
                onClick = {
                    if (comment.isBlank()) {
                        feedbackMessage = "Agrega un comentario."
                        return@Button
                    }
                    isSubmitting = true
                    coroutineScope.launch {
                        try {
                            val body = JSONObject().apply {
                                put("response_type", "accept")
                                put("commitment_comment", comment)
                            }
                            Api.request(serverUrl, "/api/mobile/evaluations/${evaluation.optInt("id")}/respond", "POST", body, token)
                            feedbackMessage = "Respuesta guardada con éxito."
                        } catch (e: Exception) {
                            feedbackMessage = "Error: ${e.message}"
                        } finally {
                            isSubmitting = false
                        }
                    }
                },
                modifier = Modifier.weight(1f)
            ) {
                Text("Aceptar")
            }
            OutlinedButton(
                onClick = {
                    if (comment.isBlank()) {
                        feedbackMessage = "Agrega un comentario para disputar."
                        return@OutlinedButton
                    }
                    isSubmitting = true
                    coroutineScope.launch {
                        try {
                            val body = JSONObject().apply {
                                put("response_type", "dispute")
                                put("dispute_reason", comment)
                            }
                            Api.request(serverUrl, "/api/mobile/evaluations/${evaluation.optInt("id")}/respond", "POST", body, token)
                            feedbackMessage = "Disputa enviada con éxito."
                        } catch (e: Exception) {
                            feedbackMessage = "Error: ${e.message}"
                        } finally {
                            isSubmitting = false
                        }
                    }
                },
                modifier = Modifier.weight(1f)
            ) {
                Text("Disputar")
            }
        }
        if (feedbackMessage != null) {
            Spacer(modifier = Modifier.height(8.dp))
            Text(feedbackMessage!!, color = MaterialTheme.colorScheme.primary)
        }
    }
}

@Composable
fun TranscriptDetail(transcript: JSONObject, token: String?) {
    SectionHeader("Detalles de Interacción", transcript.optString("file_name", "Sin archivo"))
    
    Card(modifier = Modifier.fillMaxWidth().padding(vertical = 8.dp)) {
        Column(modifier = Modifier.padding(16.dp)) {
            InfoRow("Campaña", transcript.optString("campaign", "Sin campaña"))
            InfoRow("Asesor", transcript.optString("agent", "Sin dato"))
            InfoRow("Duración", transcript.optString("duration_label", "00:00"))
            InfoRow("Estado IA", transcript.optString("transcription_status", "Sin dato"))
        }
    }

    val audioUrl = transcript.optString("audio_url")
    if (transcript.optString("source_type") == "audio" && audioUrl.isNotEmpty()) {
        Spacer(modifier = Modifier.height(16.dp))
        AudioPlayer(url = audioUrl, token = token)
    }

    Spacer(modifier = Modifier.height(16.dp))
    SectionHeader("Texto transcrito")
    val text = transcript.optString("transcript_text", transcript.optString("transcript_excerpt", "Transcripción no disponible."))
    Text(text = text, style = MaterialTheme.typography.bodyMedium)
}

@Composable
fun CampaignDetail(campaign: JSONObject) {
    SectionHeader("Detalles de Campaña", campaign.optString("name", "Campaña"))
    
    Card(modifier = Modifier.fillMaxWidth().padding(vertical = 8.dp)) {
        Column(modifier = Modifier.padding(16.dp)) {
            ProgressLine("Calidad Promedio", campaign.optDouble("average_score", 0.0), getScoreColor(campaign.optDouble("average_score", 0.0)))
            Spacer(modifier = Modifier.height(16.dp))
            InfoRow("Evaluaciones", campaign.optString("evaluations", "0"))
            InfoRow("Interacciones", campaign.optString("interactions", "0"))
            InfoRow("Estado", if (campaign.optBoolean("active")) "Activa" else "Inactiva")
        }
    }
}

