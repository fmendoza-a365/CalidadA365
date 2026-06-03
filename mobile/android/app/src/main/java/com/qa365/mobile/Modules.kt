package com.qa365.mobile

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import org.json.JSONArray
import org.json.JSONObject

@Composable
fun MainDashboardModule(data: JSONObject, onDetailSelected: (String, JSONObject) -> Unit) {
    val overview = data.optJSONObject("overview") ?: JSONObject()
    val summary = data.optJSONObject("summary") ?: JSONObject()
    val modules = data.optJSONObject("modules") ?: JSONObject()
    val feedbackModule = modules.optJSONObject("feedback") ?: JSONObject()
    val feedbackSummary = feedbackModule.optJSONObject("summary") ?: JSONObject()
    val profile = data.optJSONObject("profile") ?: JSONObject()
    val isAgent = profile.optString("primary_view", "executive") == "agent"

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
    ) {
        SectionHeader(title = if (isAgent) "Vista de Asesor" else "Vista Ejecutiva")
        
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            MetricCard(
                title = "Nota Promedio",
                value = String.format("%.1f%%", overview.optDouble("average_score", 0.0)),
                subtitle = "Periodo actual",
                color = Green,
                modifier = Modifier.weight(1f)
            )
            MetricCard(
                title = "Alertas",
                value = summary.optString("open_alerts", "0"),
                subtitle = "Por revisar",
                color = Amber,
                modifier = Modifier.weight(1f)
            )
        }

        Spacer(modifier = Modifier.height(24.dp))
        SectionHeader(title = "Métricas Principales")
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            MetricCard(
                title = "Evaluaciones",
                value = overview.optString("total_evaluations", "0"),
                subtitle = "Realizadas",
                color = Blue,
                modifier = Modifier.weight(1f)
            )
            MetricCard(
                title = "Críticas",
                value = summary.optString("critical_scores", "0"),
                subtitle = "Menor a 70%",
                color = Rose,
                modifier = Modifier.weight(1f)
            )
        }

        Spacer(modifier = Modifier.height(24.dp))
        SectionHeader(title = "Seguimiento de Feedback")
        Card(modifier = Modifier.fillMaxWidth()) {
            Column(modifier = Modifier.padding(16.dp)) {
                InfoRow("Visto", feedbackSummary.optString("viewed", "0"))
                InfoRow("Respondido", feedbackSummary.optString("responded", "0"))
                InfoRow("Pendiente", feedbackSummary.optString("pending_response", "0"))
            }
        }

        // Charts Section
        val trendArray = data.optJSONArray("quality_trend")
        if (trendArray != null && trendArray.length() > 0) {
            Spacer(modifier = Modifier.height(24.dp))
            SectionHeader(title = "Tendencia de Calidad")
            
            val trendPoints = mutableListOf<ChartPoint>()
            for (i in 0 until trendArray.length()) {
                val point = trendArray.optJSONObject(i) ?: continue
                val score = point.optDouble("avg_score", 0.0)
                trendPoints.add(
                    ChartPoint(
                        label = point.optString("label", "Día").takeLast(5),
                        value = score,
                        color = getScoreColor(score)
                    )
                )
            }
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    TrendLineChart(points = trendPoints)
                }
            }
        }

        val defectsArray = data.optJSONArray("top_defects")
        if (defectsArray != null && defectsArray.length() > 0) {
            Spacer(modifier = Modifier.height(24.dp))
            SectionHeader(title = "Hallazgos Principales")
            
            val defectPoints = mutableListOf<ChartPoint>()
            for (i in 0 until defectsArray.length()) {
                val defect = defectsArray.optJSONObject(i) ?: continue
                val isCritical = defect.optBoolean("is_critical", false)
                defectPoints.add(
                    ChartPoint(
                        label = defect.optString("label", "Criterio"),
                        value = defect.optDouble("count", 0.0),
                        color = if (isCritical) Rose else Amber
                    )
                )
            }
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    TopDefectsBarChart(defects = defectPoints)
                }
            }
        }
    }
}

@Composable
fun TranscriptsModule(data: JSONObject, onDetailSelected: (String, JSONObject) -> Unit) {
    val module = data.optJSONObject("modules")?.optJSONObject("transcripts") ?: JSONObject()
    val summary = module.optJSONObject("summary") ?: JSONObject()
    val items = module.optJSONArray("items") ?: JSONArray()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
    ) {
        SectionHeader("Transcripciones", "Carga, estado y resultado de audios.")
        
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            MetricCard("Total", summary.optString("total", "0"), "Interacciones", Blue, Modifier.weight(1f))
            MetricCard("Procesando", summary.optString("processing", "0"), "En cola IA", Amber, Modifier.weight(1f))
        }

        Spacer(modifier = Modifier.height(24.dp))
        SectionHeader("Últimas Transcripciones")
        
        for (i in 0 until items.length()) {
            val item = items.optJSONObject(i) ?: continue
            DetailCard(
                title = item.optString("campaign", "Sin campaña"),
                scoreValue = item.optString("score_label", "Sin nota"),
                scoreColor = getScoreColor(item.optDouble("score", -1.0)),
                description = "${item.optString("agent", "Sin asesor")} | ${item.optString("file_name", "Interacción")}",
                chips = listOf(item.optString("transcription_status", "Pendiente"), item.optString("duration_label", "00:00")),
                onClick = { onDetailSelected("transcript", item) }
            )
        }
    }
}

@Composable
fun EvaluationsModule(data: JSONObject, onDetailSelected: (String, JSONObject) -> Unit) {
    val module = data.optJSONObject("modules")?.optJSONObject("evaluations") ?: JSONObject()
    val summary = module.optJSONObject("summary") ?: JSONObject()
    val items = module.optJSONArray("items") ?: JSONArray()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
    ) {
        SectionHeader("Evaluaciones", "Notas, revisión y respuesta del asesor.")
        
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            MetricCard("Total", summary.optString("total", "0"), "Visibles", Blue, Modifier.weight(1f))
            MetricCard("Pendientes", summary.optString("pending_monitor", "0"), "Por revisar monitor", Amber, Modifier.weight(1f))
        }

        Spacer(modifier = Modifier.height(24.dp))
        SectionHeader("Últimas Evaluaciones")
        
        for (i in 0 until items.length()) {
            val item = items.optJSONObject(i) ?: continue
            DetailCard(
                title = item.optString("campaign", "Sin campaña"),
                scoreValue = item.optString("score_label", "0%"),
                scoreColor = getScoreColor(item.optDouble("score", -1.0)),
                description = "${item.optString("agent", "Sin asesor")} | ${item.optString("status_label", "Sin estado")}",
                chips = listOf(if (item.optJSONObject("feedback_response")?.optBoolean("responded") == true) "Respondido" else "Pendiente"),
                onClick = { onDetailSelected("evaluation", item) }
            )
        }
    }
}

@Composable
fun CampaignsModule(data: JSONObject, onDetailSelected: (String, JSONObject) -> Unit) {
    val module = data.optJSONObject("modules")?.optJSONObject("campaigns") ?: JSONObject()
    val summary = module.optJSONObject("summary") ?: JSONObject()
    val items = module.optJSONArray("items") ?: JSONArray()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
    ) {
        SectionHeader("Campañas", "Avance por operación.")
        
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            MetricCard("Activas", summary.optString("active", "0"), "En operación", Green, Modifier.weight(1f))
            MetricCard("Total", summary.optString("total", "0"), "Asignadas", Blue, Modifier.weight(1f))
        }

        Spacer(modifier = Modifier.height(24.dp))
        SectionHeader("Campañas Visibles")
        
        for (i in 0 until items.length()) {
            val item = items.optJSONObject(i) ?: continue
            DetailCard(
                title = item.optString("name", "Campaña"),
                scoreValue = item.optString("score_label", "0%"),
                scoreColor = getScoreColor(item.optDouble("average_score", 0.0)),
                description = "${item.optString("evaluations", "0")} evals | ${item.optString("interactions", "0")} interacciones",
                chips = listOf(if (item.optBoolean("active")) "Activa" else "Inactiva"),
                onClick = { onDetailSelected("campaign", item) }
            )
        }
    }
}

@Composable
fun ProfileModule(data: JSONObject, onLogout: () -> Unit, onRefresh: () -> Unit) {
    val profile = data.optJSONObject("profile") ?: JSONObject()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Spacer(modifier = Modifier.height(32.dp))
        Text(text = profile.optString("name", "Usuario QA365"), fontSize = 24.sp, fontWeight = FontWeight.Bold)
        Text(text = profile.optString("email", "Cuenta"), fontSize = 14.sp, color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.6f))
        Spacer(modifier = Modifier.height(16.dp))
        LabelChip(text = profile.optJSONArray("roles")?.optString(0, "Usuario") ?: "Usuario", color = Blue)

        Spacer(modifier = Modifier.height(48.dp))

        Button(onClick = onRefresh, modifier = Modifier.fillMaxWidth()) {
            Text("Sincronizar Datos")
        }
        Spacer(modifier = Modifier.height(16.dp))
        Button(
            onClick = onLogout,
            modifier = Modifier.fillMaxWidth(),
            colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.error)
        ) {
            Text("Cerrar Sesión")
        }
    }
}
