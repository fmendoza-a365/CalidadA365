package com.qa365.mobile

import androidx.compose.animation.core.*
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.*
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlin.math.max

data class ChartPoint(val label: String, val value: Double, val color: Color)

@Composable
fun TrendLineChart(points: List<ChartPoint>, modifier: Modifier = Modifier) {
    if (points.isEmpty()) return

    val animationProgress = remember { Animatable(0f) }
    LaunchedEffect(points) {
        animationProgress.animateTo(
            targetValue = 1f,
            animationSpec = tween(durationMillis = 1200, easing = FastOutSlowInEasing)
        )
    }

    val primaryColor = MaterialTheme.colorScheme.primary
    val surfaceColor = MaterialTheme.colorScheme.surface
    val outlineColor = MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)
    val gradientBrush = Brush.verticalGradient(
        colors = listOf(primaryColor.copy(alpha = 0.3f), Color.Transparent)
    )

    Column(modifier = modifier) {
        Canvas(modifier = Modifier.fillMaxWidth().height(180.dp).padding(vertical = 8.dp)) {
            val width = size.width
            val height = size.height
            val maxVal = 100.0

            // Draw horizontal dashed grid lines (25%, 50%, 75%, 100%)
            val gridLevels = listOf(0.25f, 0.5f, 0.75f, 1.0f)
            gridLevels.forEach { level ->
                val y = height - (level * height)
                drawLine(
                    color = outlineColor,
                    start = Offset(0f, y),
                    end = Offset(width, y),
                    strokeWidth = 1.dp.toPx(),
                    pathEffect = PathEffect.dashPathEffect(floatArrayOf(10f, 10f), 0f)
                )
            }

            val pointOffsets = points.mapIndexed { index, point ->
                val x = if (points.size > 1) (width / (points.size - 1)) * index else width / 2
                val y = height - ((point.value / maxVal) * height).toFloat()
                Offset(x, y)
            }

            val path = Path().apply {
                if (pointOffsets.isNotEmpty()) {
                    moveTo(pointOffsets.first().x, pointOffsets.first().y)
                    for (i in 0 until pointOffsets.size - 1) {
                        val p1 = pointOffsets[i]
                        val p2 = pointOffsets[i + 1]
                        val controlX = (p1.x + p2.x) / 2
                        cubicTo(controlX, p1.y, controlX, p2.y, p2.x, p2.y)
                    }
                }
            }

            // Draw Gradient Fill under the curve
            if (pointOffsets.isNotEmpty()) {
                val fillPath = Path().apply {
                    addPath(path)
                    lineTo(pointOffsets.last().x, height)
                    lineTo(pointOffsets.first().x, height)
                    close()
                }
                drawPath(
                    path = fillPath,
                    brush = gradientBrush,
                    alpha = animationProgress.value
                )
            }

            // Draw primary smoothed trend line
            drawPath(
                path = path,
                color = primaryColor,
                style = Stroke(width = 3.dp.toPx(), cap = StrokeCap.Round),
                alpha = animationProgress.value
            )

            // Draw circular nodes
            pointOffsets.forEachIndexed { index, offset ->
                if (animationProgress.value > (index.toFloat() / points.size)) {
                    drawCircle(
                        color = surfaceColor,
                        radius = 5.dp.toPx(),
                        center = offset
                    )
                    drawCircle(
                        color = points[index].color,
                        radius = 3.5.dp.toPx(),
                        center = offset
                    )
                }
            }
        }
        
        Spacer(modifier = Modifier.height(6.dp))

        // Label row
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            points.forEach { point ->
                Text(
                    text = point.label,
                    fontSize = 10.sp,
                    fontWeight = FontWeight.Medium,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                )
            }
        }
    }
}

@Composable
fun TopDefectsBarChart(defects: List<ChartPoint>, modifier: Modifier = Modifier) {
    if (defects.isEmpty()) return

    val animationProgress = remember { Animatable(0f) }
    LaunchedEffect(defects) {
        animationProgress.animateTo(
            targetValue = 1f,
            animationSpec = tween(durationMillis = 800, easing = EaseOutExpo)
        )
    }
    val outlineColor = MaterialTheme.colorScheme.outline
    Column(modifier = modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        val maxVal = max(1.0, defects.maxOfOrNull { it.value } ?: 1.0)
        
        defects.forEach { defect ->
            val percent = (defect.value / maxVal).toFloat()
            Column(modifier = Modifier.fillMaxWidth()) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = defect.label,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Medium,
                        color = MaterialTheme.colorScheme.onSurface,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = defect.value.toInt().toString(),
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        color = defect.color
                    )
                }
                Spacer(modifier = Modifier.height(4.dp))
                Canvas(modifier = Modifier.fillMaxWidth().height(8.dp)) {
                    val targetWidth = size.width * percent
                    val animatedWidth = targetWidth * animationProgress.value
                    
                    // Background Track
                    drawRoundRect(
                        color = outlineColor.copy(alpha = 0.1f),
                        size = Size(size.width, size.height),
                        cornerRadius = CornerRadius(size.height / 2, size.height / 2)
                    )
                    
                    // Animated Fill
                    drawRoundRect(
                        color = defect.color,
                        size = Size(animatedWidth, size.height),
                        cornerRadius = CornerRadius(size.height / 2, size.height / 2)
                    )
                }
            }
        }
    }
}
