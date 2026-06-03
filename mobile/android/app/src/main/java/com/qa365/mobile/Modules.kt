package com.qa365.mobile

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
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
    val name = profile.optString("name", "Usuario")
    val paternal = profile.optString("paternal_surname", "")
    val maternal = profile.optString("maternal_surname", "")
    val fullName = profile.optString("full_name", "$name $paternal $maternal".trim()).ifEmpty { "Usuario QA365" }
    val username = profile.optString("username", "")
    val email = profile.optString("email", "")
    val personalEmail = profile.optString("personal_email", "")
    val personalPhone = profile.optString("personal_phone", "")
    val companyPhone = profile.optString("company_phone", "")
    val birthdate = profile.optString("birthdate", "")
    val gender = profile.optString("gender", "")
    val address = profile.optString("address", "")
    val department = profile.optString("department", "")
    val province = profile.optString("province", "")
    val district = profile.optString("district", "")
    val avatarUrl = profile.optString("avatar_url", "")

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Spacer(modifier = Modifier.height(24.dp))
        
        // Premium Profile Avatar using Coil
        Box(
            modifier = Modifier
                .size(108.dp)
                .clip(CircleShape)
                .background(MaterialTheme.colorScheme.surfaceVariant),
            contentAlignment = Alignment.Center
        ) {
            val fallbackUrl = "https://ui-avatars.com/api/?name=${fullName.replace(" ", "+")}&color=6366F1&background=EBF4FF&bold=true&size=128"
            AsyncImage(
                model = avatarUrl.ifEmpty { fallbackUrl },
                contentDescription = "Foto de perfil",
                modifier = Modifier
                    .size(100.dp)
                    .clip(CircleShape),
                contentScale = ContentScale.Crop
            )
        }

        Spacer(modifier = Modifier.height(16.dp))
        
        Text(
            text = fullName,
            fontSize = 22.sp,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onBackground
        )
        
        if (username.isNotEmpty()) {
            Text(
                text = "@$username",
                fontSize = 14.sp,
                color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.5f)
            )
        }
        
        Spacer(modifier = Modifier.height(12.dp))
        
        val rolesArray = profile.optJSONArray("roles")
        val mainRole = if (rolesArray != null && rolesArray.length() > 0) rolesArray.optString(0, "Usuario") else "Usuario"
        LabelChip(text = mainRole.uppercase(), color = MaterialTheme.colorScheme.primary)

        Spacer(modifier = Modifier.height(32.dp))

        // Cards layout
        // Card 1: Cuenta
        Card(
            modifier = Modifier.fillMaxWidth().padding(vertical = 6.dp),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
            elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                Text("Datos de la Cuenta", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary)
                Spacer(modifier = Modifier.height(12.dp))
                InfoRow("Email Corporativo", email.ifEmpty { "—" })
                InfoRow("Rol del Sistema", mainRole)
                if (username.isNotEmpty()) {
                    InfoRow("Usuario", username)
                }
            }
        }

        // Card 2: Contacto
        if (personalPhone.isNotEmpty() || companyPhone.isNotEmpty() || personalEmail.isNotEmpty()) {
            Card(
                modifier = Modifier.fillMaxWidth().padding(vertical = 6.dp),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Text("Información de Contacto", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary)
                    Spacer(modifier = Modifier.height(12.dp))
                    if (companyPhone.isNotEmpty()) InfoRow("Teléfono Corporativo", companyPhone)
                    if (personalPhone.isNotEmpty()) InfoRow("Teléfono Personal", personalPhone)
                    if (personalEmail.isNotEmpty()) InfoRow("Email Personal", personalEmail)
                }
            }
        }

        // Card 3: Datos Personales y Ubicación
        if (birthdate.isNotEmpty() || gender.isNotEmpty() || address.isNotEmpty()) {
            Card(
                modifier = Modifier.fillMaxWidth().padding(vertical = 6.dp),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Text("Información Personal", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary)
                    Spacer(modifier = Modifier.height(12.dp))
                    if (birthdate.isNotEmpty()) InfoRow("Cumpleaños", birthdate)
                    if (gender.isNotEmpty()) InfoRow("Género", if (gender.lowercase() == "male" || gender.lowercase() == "m") "Masculino" else if (gender.lowercase() == "female" || gender.lowercase() == "f") "Femenino" else gender)
                    if (address.isNotEmpty()) InfoRow("Dirección", address)
                    
                    val location = listOf(district, province, department).filter { it.isNotEmpty() }.joinToString(", ")
                    if (location.isNotEmpty()) InfoRow("Ubicación", location)
                }
            }
        }

        Spacer(modifier = Modifier.height(32.dp))

        // Action Buttons
        Button(
            onClick = onRefresh,
            modifier = Modifier.fillMaxWidth().height(48.dp),
            shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.primary)
        ) {
            Text("Sincronizar Datos", fontSize = 15.sp, fontWeight = FontWeight.Bold)
        }
        
        Spacer(modifier = Modifier.height(12.dp))
        
        OutlinedButton(
            onClick = onLogout,
            modifier = Modifier.fillMaxWidth().height(48.dp),
            shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.outlinedButtonColors(contentColor = MaterialTheme.colorScheme.error)
        ) {
            Text("Cerrar Sesión", fontSize = 15.sp, fontWeight = FontWeight.Bold)
        }
        
        Spacer(modifier = Modifier.height(24.dp))
    }
}
