package com.qa365.mobile;

import android.app.Activity;
import android.content.SharedPreferences;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.text.TextUtils;
import android.text.method.HideReturnsTransformationMethod;
import android.text.method.PasswordTransformationMethod;
import android.view.Gravity;
import android.view.View;
import android.view.inputmethod.EditorInfo;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ImageButton;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.ScrollView;
import android.widget.TextView;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class MainActivity extends Activity {
    private static final String PREFS = "qa365_mobile";
    private static final String DEFAULT_SERVER = "https://qa365.com.pe";

    private static final int GREEN = Color.rgb(18, 184, 134);
    private static final int BLUE = Color.rgb(59, 130, 246);
    private static final int AMBER = Color.rgb(245, 158, 11);
    private static final int ROSE = Color.rgb(244, 63, 94);
    private static final int VIOLET = Color.rgb(124, 58, 237);
    private static final int CYAN = Color.rgb(6, 182, 212);

    private final Handler mainHandler = new Handler(Looper.getMainLooper());
    private final ExecutorService executor = Executors.newFixedThreadPool(3);

    private SharedPreferences prefs;
    private LinearLayout root;
    private LinearLayout content;
    private JSONObject dashboardData;
    private JSONObject detailData;
    private String token;
    private String serverUrl;
    private String activeTab = "dashboard";
    private String detailType = "";
    private boolean darkMode;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefs = getSharedPreferences(PREFS, MODE_PRIVATE);
        token = prefs.getString("token", null);
        serverUrl = prefs.getString("server_url", DEFAULT_SERVER);
        darkMode = prefs.getBoolean("dark_mode", true);
        applySystemBars();

        if (token == null || token.isEmpty()) {
            showLogin();
        } else {
            showDashboard();
        }
    }

    @Override
    protected void onDestroy() {
        executor.shutdownNow();
        super.onDestroy();
    }

    private void showLogin() {
        applySystemBars();
        root = vertical();
        root.setBackgroundColor(bg());
        root.setPadding(dp(20), dp(18), dp(20), dp(18));

        LinearLayout top = horizontal();
        top.setGravity(Gravity.CENTER_VERTICAL);
        top.addView(text("QA365", 14, muted(), Typeface.BOLD), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
        top.addView(themeIconButton(), new LinearLayout.LayoutParams(dp(42), dp(42)));
        root.addView(top, matchWrap());

        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        scroll.setVerticalScrollBarEnabled(false);
        LinearLayout body = vertical();
        body.setGravity(Gravity.CENTER_HORIZONTAL);
        body.setPadding(0, dp(30), 0, dp(20));
        scroll.addView(body, matchWrap());
        root.addView(scroll, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, 0, 1));

        ImageView logo = logoView(172, 76);
        LinearLayout.LayoutParams logoParams = wrapWrap();
        logoParams.setMargins(0, 0, 0, dp(18));
        body.addView(logo, logoParams);

        TextView title = text("Centro movil de calidad", 24, textColor(), Typeface.BOLD);
        title.setGravity(Gravity.CENTER);
        title.setMaxLines(2);
        body.addView(title, matchWrap());

        TextView subtitle = text("Seguimiento ejecutivo y resultados de asesores", 14, muted(), Typeface.NORMAL);
        subtitle.setGravity(Gravity.CENTER);
        subtitle.setMaxLines(3);
        LinearLayout.LayoutParams subtitleParams = matchWrap();
        subtitleParams.setMargins(0, dp(8), 0, dp(22));
        body.addView(subtitle, subtitleParams);

        LinearLayout card = card();
        card.setPadding(dp(16), dp(16), dp(16), dp(16));

        EditText serverInput = input("Servidor");
        serverInput.setText(serverUrl);
        card.addView(serverInput, matchWrap());

        EditText loginInput = input("Usuario o email");
        card.addView(loginInput, spaced());

        PasswordField password = passwordField("Contrasena");
        password.input.setImeOptions(EditorInfo.IME_ACTION_DONE);
        card.addView(password.container, spaced());

        Button loginButton = primaryButton("Ingresar");
        LinearLayout.LayoutParams buttonParams = matchWrap();
        buttonParams.setMargins(0, dp(16), 0, 0);
        card.addView(loginButton, buttonParams);

        TextView status = text("", 13, muted(), Typeface.NORMAL);
        status.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams statusParams = matchWrap();
        statusParams.setMargins(0, dp(12), 0, 0);
        card.addView(status, statusParams);

        body.addView(card, matchWrap());
        setContentView(root);

        View.OnClickListener loginAction = v -> {
            String typedServer = normalizeServer(serverInput.getText().toString());
            String login = loginInput.getText().toString().trim();
            String passwordText = password.input.getText().toString();

            if (typedServer.isEmpty() || login.isEmpty() || passwordText.isEmpty()) {
                status.setText("Completa servidor, usuario y contrasena.");
                return;
            }

            status.setText("Validando credenciales...");
            loginButton.setEnabled(false);
            executor.execute(() -> {
                try {
                    serverUrl = typedServer;
                    JSONObject bodyJson = new JSONObject();
                    bodyJson.put("login", login);
                    bodyJson.put("password", passwordText);
                    bodyJson.put("device_name", "QA365 Android");

                    JSONObject response = request("POST", "/api/mobile/login", bodyJson);
                    token = response.getString("access_token");
                    prefs.edit()
                        .putString("token", token)
                        .putString("server_url", serverUrl)
                        .apply();
                    activeTab = "dashboard";
                    detailType = "";
                    detailData = null;
                    runOnMain(this::showDashboard);
                } catch (Exception ex) {
                    runOnMain(() -> {
                        loginButton.setEnabled(true);
                        status.setText(cleanError(ex));
                    });
                }
            });
        };

        loginButton.setOnClickListener(loginAction);
        password.input.setOnEditorActionListener((v, actionId, event) -> {
            if (actionId == EditorInfo.IME_ACTION_DONE) {
                loginAction.onClick(loginButton);
                return true;
            }
            return false;
        });
    }

    private void showDashboard() {
        buildDashboardShell();
        renderLoading();
        loadDashboard();
    }

    private void buildDashboardShell() {
        applySystemBars();
        root = vertical();
        root.setBackgroundColor(bg());
        root.addView(dashboardHeader(), matchWrap());

        ScrollView scrollView = new ScrollView(this);
        scrollView.setVerticalScrollBarEnabled(false);
        content = vertical();
        content.setPadding(dp(16), dp(10), dp(16), dp(14));
        scrollView.addView(content, matchWrap());
        root.addView(scrollView, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, 0, 1));
        root.addView(bottomNavigation(), matchWrap());

        setContentView(root);
    }

    private LinearLayout dashboardHeader() {
        JSONObject profile = object(dashboardData, "profile");
        String name = nonEmpty(profile.optString("name"), "QA365");
        String role = profile.optJSONArray("roles") != null && profile.optJSONArray("roles").length() > 0
            ? profile.optJSONArray("roles").optString(0, "Usuario")
            : "Centro movil";

        LinearLayout header = horizontal();
        header.setGravity(Gravity.CENTER_VERTICAL);
        header.setPadding(dp(16), dp(14), dp(16), dp(10));
        header.setBackgroundColor(bg());

        ImageView avatar = avatarView(profile, 42);
        header.addView(avatar, new LinearLayout.LayoutParams(dp(42), dp(42)));

        LinearLayout titleBox = vertical();
        titleBox.setPadding(dp(10), 0, 0, 0);
        TextView title = text(name, 15, textColor(), Typeface.BOLD);
        title.setSingleLine(true);
        title.setEllipsize(TextUtils.TruncateAt.END);
        titleBox.addView(title, matchWrap());
        TextView subtitle = text(roleLabel(role), 12, muted(), Typeface.NORMAL);
        subtitle.setSingleLine(true);
        subtitle.setEllipsize(TextUtils.TruncateAt.END);
        titleBox.addView(subtitle, matchWrap());
        header.addView(titleBox, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));

        header.addView(themeIconButton(), new LinearLayout.LayoutParams(dp(40), dp(40)));
        header.addView(headerIconButton(R.drawable.ic_refresh, "Actualizar", v -> loadDashboard()), marginLeft(dp(8), dp(40), dp(40)));
        header.addView(headerIconButton(R.drawable.ic_logout, "Salir", v -> logout()), marginLeft(dp(8), dp(40), dp(40)));

        return header;
    }

    private void loadDashboard() {
        if (content == null) {
            return;
        }

        executor.execute(() -> {
            try {
                JSONObject response = request("GET", "/api/mobile/dashboard", null);
                runOnMain(() -> {
                    dashboardData = response;
                    renderDashboard();
                });
            } catch (ApiException ex) {
                if (ex.code == 401) {
                    clearSession();
                    runOnMain(this::showLogin);
                    return;
                }
                runOnMain(() -> renderError(ex.getMessage()));
            } catch (Exception ex) {
                runOnMain(() -> renderError(cleanError(ex)));
            }
        });
    }

    private void renderDashboard() {
        buildDashboardShell();
        content.removeAllViews();

        if (detailData != null) {
            renderDetailScreen();
        } else if ("transcripts".equals(activeTab)) {
            renderTranscriptsModule();
        } else if ("evaluations".equals(activeTab)) {
            renderEvaluationsModule();
        } else if ("campaigns".equals(activeTab)) {
            renderCampaignsModule();
        } else if ("more".equals(activeTab)) {
            renderMoreModule();
        } else {
            renderDashboardModule();
        }
    }

    private void renderDashboardModule() {
        JSONObject profile = object(dashboardData, "profile");
        boolean isAgent = "agent".equals(profile.optString("primary_view", "executive"));
        JSONObject overview = object(dashboardData, "overview");
        JSONObject summary = object(dashboardData, "summary");
        JSONObject feedback = object(dashboardData, "feedback");
        JSONObject modules = object(dashboardData, "modules");
        JSONObject feedbackModule = object(modules, "feedback");
        JSONObject feedbackSummary = object(feedbackModule, "summary");

        content.addView(heroCard(profile, isAgent, overview, summary), matchWrap());

        LinearLayout grid = vertical();
        grid.addView(metricRow(
            metricCard("Evaluaciones", overview.optString("total_evaluations", "0"), "Periodo actual", BLUE),
            metricCard("Feedback", percentText(feedback.opt("done_pct")), "Visto por asesores", GREEN)
        ));
        grid.addView(metricRow(
            metricCard("Pendientes", feedbackSummary.optString("pending_response", "0"), "Feedback sin respuesta", AMBER),
            metricCard("Criticas", summary.optString("critical_scores", "0"), "Menor a 70%", ROSE)
        ));
        content.addView(grid, sectionParams());

        content.addView(moduleGrid(modules), sectionParams());
        renderFeedbackBlock(feedbackSummary);
        renderTrend();
        renderDefects();
    }

    private LinearLayout heroCard(JSONObject profile, boolean isAgent, JSONObject overview, JSONObject summary) {
        LinearLayout hero = card();
        hero.setPadding(dp(16), dp(16), dp(16), dp(16));

        LinearLayout top = horizontal();
        top.setGravity(Gravity.CENTER_VERTICAL);

        LinearLayout titleBox = vertical();
        TextView scope = text(isAgent ? "Vista de asesor" : "Vista ejecutiva", 12, accent(), Typeface.BOLD);
        titleBox.addView(scope, matchWrap());
        TextView name = text(nonEmpty(profile.optString("name"), "Usuario QA365"), 23, textColor(), Typeface.BOLD);
        name.setMaxLines(2);
        titleBox.addView(name, spacedSmall());
        TextView caption = text(isAgent
            ? "Tus resultados, feedback y progreso personal."
            : "Calidad, avance operativo, feedback y alertas.",
            13, muted(), Typeface.NORMAL);
        caption.setMaxLines(3);
        titleBox.addView(caption, spacedSmall());
        top.addView(titleBox, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
        top.addView(avatarView(profile, 58), new LinearLayout.LayoutParams(dp(58), dp(58)));
        hero.addView(top, matchWrap());

        LinearLayout metrics = horizontal();
        metrics.addView(compactMetric("Nota", percentText(overview.opt("average_score")), GREEN), weightParams(1, 0, dp(5), dp(16), 0));
        metrics.addView(compactMetric("Alertas", summary.optString("open_alerts", "0"), AMBER), weightParams(1, dp(5), 0, dp(16), 0));
        hero.addView(metrics, matchWrap());

        return hero;
    }

    private LinearLayout moduleGrid(JSONObject modules) {
        LinearLayout section = section("Modulos");
        section.addView(metricRow(
            moduleTile("Transcripciones", object(object(modules, "transcripts"), "summary").optString("total", "0"), "Audios y textos", "transcripts", CYAN),
            moduleTile("Evaluaciones", object(object(modules, "evaluations"), "summary").optString("total", "0"), "Notas y feedback", "evaluations", BLUE)
        ), spaced());
        section.addView(metricRow(
            moduleTile("Campanas", object(object(modules, "campaigns"), "summary").optString("active", "0"), "Activas", "campaigns", VIOLET),
            moduleTile("Fichas", object(object(modules, "quality_forms"), "summary").optString("total", "0"), "Calidad", "more", AMBER)
        ), spacedSmall());
        section.addView(metricRow(
            moduleTile("Insights", object(object(modules, "insights"), "summary").optString("total", "0"), "Reportes IA", "more", GREEN),
            moduleTile("Feedback", object(object(modules, "feedback"), "summary").optString("responded", "0"), "Respuestas", "more", ROSE)
        ), spacedSmall());
        return section;
    }

    private LinearLayout moduleTile(String title, String value, String detail, String tab, int color) {
        LinearLayout tile = compactCard();
        tile.setOnClickListener(v -> {
            activeTab = tab;
            detailType = "";
            detailData = null;
            renderDashboard();
        });
        TextView label = text(title, 12, muted(), Typeface.BOLD);
        label.setSingleLine(true);
        label.setEllipsize(TextUtils.TruncateAt.END);
        tile.addView(label, matchWrap());
        tile.addView(text(value, 24, color, Typeface.BOLD), spacedSmall());
        tile.addView(text(detail, 11, muted(), Typeface.NORMAL), spacedSmall());
        return tile;
    }

    private void renderFeedbackBlock(JSONObject feedbackSummary) {
        LinearLayout section = section("Seguimiento de feedback");
        section.addView(metricRow(
            compactMetric("Publicado", feedbackSummary.optString("published", "0"), BLUE),
            compactMetric("Visto", feedbackSummary.optString("viewed", "0"), GREEN)
        ), spaced());
        section.addView(metricRow(
            compactMetric("Respondido", feedbackSummary.optString("responded", "0"), GREEN),
            compactMetric("Pendiente", feedbackSummary.optString("pending_response", "0"), AMBER)
        ), spacedSmall());
        section.addView(infoRow("Aceptados", feedbackSummary.optString("accepted", "0")), spaced());
        section.addView(infoRow("Disputados", feedbackSummary.optString("disputed", "0")), spacedSmall());
        content.addView(section, sectionParams());
    }

    private void renderTranscriptsModule() {
        JSONObject module = object(object(dashboardData, "modules"), "transcripts");
        JSONObject summary = object(module, "summary");
        JSONArray items = module.optJSONArray("items");

        content.addView(moduleHeader("Transcripciones", "Carga, estado y resultado de audios."), matchWrap());
        LinearLayout grid = vertical();
        grid.addView(metricRow(
            metricCard("Total", summary.optString("total", "0"), "Interacciones visibles", BLUE),
            metricCard("Audios", summary.optString("audio", "0"), "Con archivo", CYAN)
        ));
        grid.addView(metricRow(
            metricCard("Procesando", summary.optString("processing", "0"), "En cola IA", AMBER),
            metricCard("Fallidas", summary.optString("failed", "0"), "Requieren revision", ROSE)
        ));
        content.addView(grid, sectionParams());

        LinearLayout list = section("Ultimas transcripciones");
        if (items == null || items.length() == 0) {
            list.addView(emptyText("No hay transcripciones visibles."), spaced());
        } else {
            for (int i = 0; i < items.length(); i++) {
                JSONObject item = items.optJSONObject(i);
                if (item == null) {
                    continue;
                }
                LinearLayout card = detailCard("transcript", item);
                card.addView(rowTitleValue(nonEmpty(item.optString("campaign"), "Sin campana"), item.optString("score_label", "Sin nota"), scoreColor(item.optDouble("score", -1))), matchWrap());
                card.addView(bodyText(nonEmpty(item.optString("agent"), "Sin asesor")), spacedSmall());
                card.addView(bodyText(nonEmpty(item.optString("file_name"), item.optString("source_type", "Interaccion"))), spacedSmall());
                card.addView(chipRow(new String[]{
                    "Estado: " + nonEmpty(item.optString("transcription_status"), "sin dato"),
                    "Duracion: " + nonEmpty(item.optString("duration_label"), "00:00")
                }), spaced());
                list.addView(card, spaced());
            }
        }
        content.addView(list, sectionParams());
    }

    private void renderEvaluationsModule() {
        JSONObject module = object(object(dashboardData, "modules"), "evaluations");
        JSONObject summary = object(module, "summary");
        JSONArray items = module.optJSONArray("items");

        content.addView(moduleHeader("Evaluaciones", "Notas, revision monitor y respuesta del asesor."), matchWrap());
        LinearLayout grid = vertical();
        grid.addView(metricRow(
            metricCard("Total", summary.optString("total", "0"), "Visibles", BLUE),
            metricCard("Publicadas", summary.optString("published", "0"), "Para asesor", GREEN)
        ));
        grid.addView(metricRow(
            metricCard("Pend. monitor", summary.optString("pending_monitor", "0"), "Por revisar", AMBER),
            metricCard("Criticas", summary.optString("critical", "0"), "Menor a 70%", ROSE)
        ));
        content.addView(grid, sectionParams());

        LinearLayout list = section("Ultimas evaluaciones");
        renderEvaluationList(list, items);
        content.addView(list, sectionParams());
    }

    private void renderCampaignsModule() {
        JSONObject module = object(object(dashboardData, "modules"), "campaigns");
        JSONObject summary = object(module, "summary");
        JSONArray items = module.optJSONArray("items");

        content.addView(moduleHeader("Campanas", "Avance por operacion y calidad objetivo."), matchWrap());
        LinearLayout grid = vertical();
        grid.addView(metricRow(
            metricCard("Total", summary.optString("total", "0"), "Asignadas", BLUE),
            metricCard("Activas", summary.optString("active", "0"), "En operacion", GREEN)
        ));
        content.addView(grid, sectionParams());

        LinearLayout list = section("Campanas visibles");
        if (items == null || items.length() == 0) {
            list.addView(emptyText("No hay campanas visibles."), spaced());
        } else {
            for (int i = 0; i < items.length(); i++) {
                JSONObject campaign = items.optJSONObject(i);
                if (campaign == null) {
                    continue;
                }
                LinearLayout item = detailCard("campaign", campaign);
                item.addView(rowTitleValue(nonEmpty(campaign.optString("name"), "Campana"), campaign.optString("score_label", "0%"), scoreColor(campaign.optDouble("average_score", 0))), matchWrap());
                item.addView(progressLine("Calidad promedio", campaign.optDouble("average_score", 0), scoreColor(campaign.optDouble("average_score", 0))), spaced());
                item.addView(chipRow(new String[]{
                    campaign.optString("evaluations", "0") + " evals",
                    campaign.optString("interactions", "0") + " interacciones",
                    campaign.optBoolean("active") ? "Activa" : "Inactiva"
                }), spaced());
                list.addView(item, spaced());
            }
        }
        content.addView(list, sectionParams());
    }

    private void renderMoreModule() {
        JSONObject modules = object(dashboardData, "modules");
        JSONObject forms = object(modules, "quality_forms");
        JSONObject insights = object(modules, "insights");

        content.addView(profileCard(), matchWrap());
        renderForms(forms);
        renderInsights(insights);
        renderAlerts();
        renderRanking();
    }

    private LinearLayout profileCard() {
        JSONObject profile = object(dashboardData, "profile");
        LinearLayout card = section("Perfil");
        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);
        row.addView(avatarView(profile, 64), new LinearLayout.LayoutParams(dp(64), dp(64)));
        LinearLayout info = vertical();
        info.setPadding(dp(12), 0, 0, 0);
        info.addView(text(nonEmpty(profile.optString("name"), "Usuario"), 18, textColor(), Typeface.BOLD), matchWrap());
        info.addView(text(nonEmpty(profile.optString("email"), ""), 12, muted(), Typeface.NORMAL), spacedSmall());
        info.addView(text("Foto de perfil sincronizada con la web.", 12, muted(), Typeface.NORMAL), spacedSmall());
        row.addView(info, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
        card.addView(row, spaced());
        return card;
    }

    private void renderForms(JSONObject forms) {
        JSONObject summary = object(forms, "summary");
        JSONArray items = forms.optJSONArray("items");
        content.addView(moduleHeader("Fichas de calidad", summary.optString("total", "0") + " fichas disponibles"), sectionParams());

        LinearLayout list = section("Fichas recientes");
        if (items == null || items.length() == 0) {
            list.addView(emptyText("No hay fichas visibles."), spaced());
        } else {
            for (int i = 0; i < items.length(); i++) {
                JSONObject form = items.optJSONObject(i);
                if (form == null) {
                    continue;
                }
                LinearLayout item = detailCard("form", form);
                item.addView(rowTitleValue(nonEmpty(form.optString("name"), "Ficha"), form.optString("versions", "0") + " v.", BLUE), matchWrap());
                item.addView(bodyText(nonEmpty(form.optString("campaign"), "Sin campana")), spacedSmall());
                item.addView(chipRow(new String[]{
                    nonEmpty(form.optString("latest_status"), "Sin version"),
                    form.optBoolean("has_context") ? "Con contexto" : "Sin contexto"
                }), spaced());
                list.addView(item, spaced());
            }
        }
        content.addView(list, sectionParams());
    }

    private void renderInsights(JSONObject insights) {
        JSONObject summary = object(insights, "summary");
        JSONArray items = insights.optJSONArray("items");
        content.addView(moduleHeader("Insights IA", summary.optString("last_30_days", "0") + " reportes en 30 dias"), sectionParams());

        LinearLayout list = section("Reportes recientes");
        if (items == null || items.length() == 0) {
            list.addView(emptyText("No hay insights visibles."), spaced());
        } else {
            for (int i = 0; i < items.length(); i++) {
                JSONObject insight = items.optJSONObject(i);
                if (insight == null) {
                    continue;
                }
                LinearLayout item = detailCard("insight", insight);
                item.addView(rowTitleValue(nonEmpty(insight.optString("campaign"), "Campana"), nonEmpty(insight.optString("type"), "Reporte"), VIOLET), matchWrap());
                item.addView(bodyText(nonEmpty(insight.optString("summary"), "Sin resumen disponible")), spacedSmall());
                item.addView(chipRow(new String[]{
                    nonEmpty(insight.optString("date_range"), "Sin rango"),
                    insight.optString("findings", "0") + " hallazgos"
                }), spaced());
                list.addView(item, spaced());
            }
        }
        content.addView(list, sectionParams());
    }

    private void renderEvaluationList(LinearLayout section, JSONArray results) {
        if (results == null || results.length() == 0) {
            section.addView(emptyText("Sin evaluaciones visibles."), spaced());
            return;
        }

        for (int i = 0; i < results.length(); i++) {
            JSONObject evaluation = results.optJSONObject(i);
            if (evaluation == null) {
                continue;
            }
            LinearLayout item = detailCard("evaluation", evaluation);
            item.addView(rowTitleValue(nonEmpty(evaluation.optString("campaign"), "Sin campana"), evaluation.optString("score_label", "Sin nota"), scoreColor(evaluation.optDouble("score", -1))), matchWrap());

            String agent = nonEmpty(evaluation.optString("agent"), "Sin asesor");
            String status = nonEmpty(evaluation.optString("status_label"), "Sin estado");
            item.addView(bodyText(agent + " | " + status), spacedSmall());

            JSONObject response = object(evaluation, "feedback_response");
            String feedback = response.optBoolean("responded")
                ? ("Feedback: " + nonEmpty(response.optString("type"), "respondido"))
                : (evaluation.optString("visible_to_agent_at", "").isEmpty() ? "No publicado al asesor" : "Feedback pendiente");
            JSONObject audio = object(evaluation, "audio");
            item.addView(chipRow(new String[]{
                feedback,
                "Tiempo muerto: " + nonEmpty(audio.optString("dead_air_label"), "00:00")
            }), spaced());
            section.addView(item, spaced());
        }
    }

    private void renderTrend() {
        JSONArray trend = dashboardData.optJSONArray("quality_trend");
        LinearLayout section = section("Tendencia");
        if (trend == null || trend.length() == 0) {
            section.addView(emptyText("Sin tendencia en el periodo."), spaced());
        } else {
            for (int i = 0; i < trend.length(); i++) {
                JSONObject point = trend.optJSONObject(i);
                if (point == null) {
                    continue;
                }
                section.addView(progressLine(shortLabel(point.optString("label", "Dia")), point.optDouble("avg_score", 0), scoreColor(point.optDouble("avg_score", 0))), spaced());
            }
        }
        content.addView(section, sectionParams());
    }

    private void renderDefects() {
        JSONArray defects = dashboardData.optJSONArray("top_defects");
        LinearLayout section = section("Hallazgos principales");
        if (defects == null || defects.length() == 0) {
            section.addView(emptyText("Sin hallazgos registrados."), spaced());
        } else {
            for (int i = 0; i < defects.length(); i++) {
                JSONObject defect = defects.optJSONObject(i);
                if (defect == null) {
                    continue;
                }
                int color = defect.optBoolean("is_critical") ? ROSE : AMBER;
                section.addView(twoLineRow(nonEmpty(defect.optString("label"), "Criterio"), defect.optString("count", "0"), defect.optBoolean("is_critical") ? "Critico" : "No conforme", color), spaced());
            }
        }
        content.addView(section, sectionParams());
    }

    private void renderAlerts() {
        JSONArray alerts = dashboardData.optJSONArray("alerts");
        LinearLayout section = section("Alertas");
        if (alerts == null || alerts.length() == 0) {
            section.addView(emptyText("No hay alertas abiertas."), spaced());
        } else {
            for (int i = 0; i < alerts.length(); i++) {
                JSONObject alert = alerts.optJSONObject(i);
                if (alert == null) {
                    continue;
                }
                int color = severityColor(alert.optString("severity", "info"));
                LinearLayout item = detailCard("alert", alert);
                item.addView(rowTitleValue(nonEmpty(alert.optString("title"), "Alerta"), alert.optString("severity", "INFO").toUpperCase(Locale.US), color), matchWrap());
                item.addView(bodyText(nonEmpty(alert.optString("description"), "Requiere revision.")), spacedSmall());
                section.addView(item, spaced());
            }
        }
        content.addView(section, sectionParams());
    }

    private void renderRanking() {
        JSONArray ranking = dashboardData.optJSONArray("ranking");
        LinearLayout section = section("Ranking");
        if (ranking == null || ranking.length() == 0) {
            section.addView(emptyText("No hay ranking disponible."), spaced());
        } else {
            for (int i = 0; i < ranking.length(); i++) {
                JSONObject row = ranking.optJSONObject(i);
                if (row == null) {
                    continue;
                }
                double score = row.optDouble("avg_score", 0);
                section.addView(twoLineRow(
                    row.optString("position", String.valueOf(i + 1)) + ". " + nonEmpty(row.optString("label"), "Asesor"),
                    percentText(score),
                    row.optString("total_evals", "0") + " evals | " + row.optString("level", "Nivel"),
                    scoreColor(score)
                ), spaced());
            }
        }
        content.addView(section, sectionParams());
    }

    private LinearLayout moduleHeader(String title, String subtitle) {
        LinearLayout header = card();
        header.setPadding(dp(16), dp(16), dp(16), dp(16));
        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);
        LinearLayout copy = vertical();
        copy.addView(text(title, 22, textColor(), Typeface.BOLD), matchWrap());
        TextView sub = text(subtitle, 13, muted(), Typeface.NORMAL);
        sub.setMaxLines(3);
        copy.addView(sub, spacedSmall());
        row.addView(copy, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
        header.addView(row, matchWrap());
        return header;
    }

    private void renderDetailScreen() {
        content.addView(detailHeader(detailTitle(), detailSubtitle()), matchWrap());

        if ("evaluation".equals(detailType)) {
            renderEvaluationDetail(detailData);
        } else if ("transcript".equals(detailType)) {
            renderTranscriptDetail(detailData);
        } else if ("campaign".equals(detailType)) {
            renderCampaignDetail(detailData);
        } else if ("form".equals(detailType)) {
            renderFormDetail(detailData);
        } else if ("insight".equals(detailType)) {
            renderInsightDetail(detailData);
        } else if ("alert".equals(detailType)) {
            renderAlertDetail(detailData);
        }
    }

    private LinearLayout detailHeader(String title, String subtitle) {
        LinearLayout header = card();
        header.setPadding(dp(14), dp(14), dp(14), dp(14));

        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);
        Button back = smallButton("Volver");
        back.setOnClickListener(v -> {
            detailType = "";
            detailData = null;
            renderDashboard();
        });
        row.addView(back, wrapWrap());

        LinearLayout copy = vertical();
        copy.setPadding(dp(12), 0, 0, 0);
        copy.addView(text(title, 20, textColor(), Typeface.BOLD), matchWrap());
        TextView sub = text(subtitle, 12, muted(), Typeface.NORMAL);
        sub.setMaxLines(2);
        copy.addView(sub, spacedSmall());
        row.addView(copy, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
        header.addView(row, matchWrap());

        return header;
    }

    private void renderEvaluationDetail(JSONObject evaluation) {
        maybeMarkEvaluationViewed(evaluation);

        JSONObject audio = object(evaluation, "audio");
        JSONObject indicators = object(evaluation, "feedback_indicators");
        JSONObject response = object(evaluation, "feedback_response");
        double score = evaluation.optDouble("score", -1);

        LinearLayout result = section("Resultado de calidad");
        result.addView(rowTitleValue(nonEmpty(evaluation.optString("campaign"), "Sin campana"), evaluation.optString("score_label", "Sin nota"), scoreColor(score)), spaced());
        result.addView(bodyText(nonEmpty(evaluation.optString("summary"), "Sin resumen disponible.")), spaced());
        result.addView(progressLine("Nota", score < 0 ? 0 : score, scoreColor(score)), spaced());
        result.addView(chipRow(new String[]{
            nonEmpty(evaluation.optString("status_label"), "Sin estado"),
            "Asesor: " + nonEmpty(evaluation.optString("agent"), "Sin dato"),
            "Monitor: " + nonEmpty(evaluation.optString("evaluator"), "Sin dato")
        }), spaced());
        content.addView(result, sectionParams());

        LinearLayout audioBox = section("Audio y tiempos");
        audioBox.addView(infoRow("Duracion", formatDurationFromJson(audio.opt("duration_seconds"))), spaced());
        audioBox.addView(infoRow("Tiempo muerto", nonEmpty(audio.optString("dead_air_label"), "00:00")), spacedSmall());
        audioBox.addView(infoRow("Silencio operativo", percentText(audio.optDouble("silence_ratio", 0) * 100)), spacedSmall());
        audioBox.addView(infoRow("Origen", nonEmpty(audio.optString("source_type"), "Sin dato")), spacedSmall());
        content.addView(audioBox, sectionParams());

        LinearLayout feedback = section("Indicadores de feedback");
        feedback.addView(indicatorRow("Empatia", indicators.optString("empathy")), spaced());
        feedback.addView(indicatorRow("Escucha activa", indicators.optString("active_listening")), spacedSmall());
        feedback.addView(indicatorRow("Objeciones", indicators.optString("objection_handling")), spacedSmall());
        feedback.addView(indicatorRow("Claridad de solucion", indicators.optString("resolution_clarity")), spacedSmall());
        feedback.addView(indicatorRow("Control del speech", indicators.optString("speech_control")), spacedSmall());
        feedback.addView(indicatorRow("Riesgo experiencia", indicators.optString("customer_experience_risk")), spacedSmall());
        feedback.addView(indicatorRow("Cliente sin resolver", booleanLabel(indicators.opt("customer_left_unresolved"))), spacedSmall());
        content.addView(feedback, sectionParams());

        LinearLayout state = section("Seguimiento del asesor");
        state.addView(infoRow("Publicado", nonEmpty(evaluation.optString("visible_to_agent_at"), "No publicado")), spaced());
        state.addView(infoRow("Visto", nonEmpty(evaluation.optString("agent_viewed_at"), "Pendiente")), spacedSmall());
        state.addView(infoRow("Respuesta", response.optBoolean("responded") ? response.optString("type", "respondido") : "Pendiente"), spacedSmall());
        content.addView(state, sectionParams());

        if (canRespondFeedback(evaluation, response)) {
            renderFeedbackForm(evaluation);
        }
    }

    private void renderTranscriptDetail(JSONObject transcript) {
        LinearLayout card = section("Transcripcion");
        card.addView(rowTitleValue(nonEmpty(transcript.optString("campaign"), "Sin campana"), transcript.optString("score_label", "Sin nota"), scoreColor(transcript.optDouble("score", -1))), spaced());
        card.addView(infoRow("Asesor", nonEmpty(transcript.optString("agent"), "Sin dato")), spaced());
        card.addView(infoRow("Supervisor", nonEmpty(transcript.optString("supervisor"), "Sin dato")), spacedSmall());
        card.addView(infoRow("Archivo", nonEmpty(transcript.optString("file_name"), "Sin archivo")), spacedSmall());
        card.addView(infoRow("Estado audio", nonEmpty(transcript.optString("status"), "Sin estado")), spacedSmall());
        card.addView(infoRow("Estado IA", nonEmpty(transcript.optString("transcription_status"), "Sin dato")), spacedSmall());
        card.addView(infoRow("Duracion", nonEmpty(transcript.optString("duration_label"), "00:00")), spacedSmall());
        content.addView(card, sectionParams());
    }

    private void renderCampaignDetail(JSONObject campaign) {
        LinearLayout card = section("Campana");
        card.addView(rowTitleValue(nonEmpty(campaign.optString("name"), "Campana"), campaign.optString("score_label", "0%"), scoreColor(campaign.optDouble("average_score", 0))), spaced());
        card.addView(progressLine("Calidad promedio", campaign.optDouble("average_score", 0), scoreColor(campaign.optDouble("average_score", 0))), spaced());
        card.addView(infoRow("Evaluaciones", campaign.optString("evaluations", "0")), spaced());
        card.addView(infoRow("Interacciones", campaign.optString("interactions", "0")), spacedSmall());
        card.addView(infoRow("Fichas", campaign.optString("forms", "0")), spacedSmall());
        card.addView(infoRow("Objetivo", campaign.isNull("target_quality") ? "Sin objetivo" : percentText(campaign.optDouble("target_quality", 0))), spacedSmall());
        card.addView(infoRow("Estado", campaign.optBoolean("active") ? "Activa" : "Inactiva"), spacedSmall());
        content.addView(card, sectionParams());
    }

    private void renderFormDetail(JSONObject form) {
        LinearLayout card = section("Ficha de calidad");
        card.addView(rowTitleValue(nonEmpty(form.optString("name"), "Ficha"), form.optString("versions", "0") + " versiones", BLUE), spaced());
        card.addView(infoRow("Campana", nonEmpty(form.optString("campaign"), "Sin campana")), spaced());
        card.addView(infoRow("Version vigente", nonEmpty(form.optString("latest_status"), "Sin version")), spacedSmall());
        card.addView(infoRow("Contexto operativo", form.optBoolean("has_context") ? "Configurado" : "Pendiente"), spacedSmall());
        content.addView(card, sectionParams());
    }

    private void renderInsightDetail(JSONObject insight) {
        LinearLayout card = section("Insight IA");
        card.addView(rowTitleValue(nonEmpty(insight.optString("campaign"), "Campana"), nonEmpty(insight.optString("type"), "Reporte"), VIOLET), spaced());
        card.addView(bodyText(nonEmpty(insight.optString("summary"), "Sin resumen disponible.")), spaced());
        card.addView(infoRow("Rango", nonEmpty(insight.optString("date_range"), "Sin rango")), spaced());
        card.addView(infoRow("Hallazgos", insight.optString("findings", "0")), spacedSmall());
        content.addView(card, sectionParams());
    }

    private void renderAlertDetail(JSONObject alert) {
        int color = severityColor(alert.optString("severity", "info"));
        LinearLayout card = section("Alerta operativa");
        card.addView(rowTitleValue(nonEmpty(alert.optString("title"), "Alerta"), alert.optString("severity", "INFO").toUpperCase(Locale.US), color), spaced());
        card.addView(bodyText(nonEmpty(alert.optString("description"), "Requiere revision.")), spaced());
        card.addView(infoRow("Campana", nonEmpty(alert.optString("campaign"), "Sin dato")), spaced());
        card.addView(infoRow("Asesor", nonEmpty(alert.optString("agent"), "Sin dato")), spacedSmall());
        card.addView(infoRow("Estado", nonEmpty(alert.optString("status_label"), "Sin estado")), spacedSmall());
        if (alert.has("evaluation_id")) {
            Button open = primaryButton("Ver evaluacion en la app");
            open.setOnClickListener(v -> openEvaluationFromAlert(alert.optInt("evaluation_id", 0)));
            card.addView(open, spaced());
        }
        content.addView(card, sectionParams());
    }

    private void renderFeedbackForm(JSONObject evaluation) {
        LinearLayout form = section("Responder feedback");
        form.addView(bodyText("La respuesta se guarda en el sistema y se refleja tambien en la web."), spaced());
        EditText comment = multiLineInput("Comentario, compromiso o motivo de disputa");
        form.addView(comment, spaced());
        TextView status = text("", 12, muted(), Typeface.NORMAL);

        LinearLayout actions = horizontal();
        Button accept = primaryButton("Aceptar");
        Button dispute = smallButton("Disputar");
        accept.setOnClickListener(v -> submitFeedback(evaluation.optInt("id", 0), "accept", comment.getText().toString(), status));
        dispute.setOnClickListener(v -> submitFeedback(evaluation.optInt("id", 0), "dispute", comment.getText().toString(), status));
        actions.addView(accept, weightParams(1, 0, dp(5), dp(10), 0));
        actions.addView(dispute, weightParams(1, dp(5), 0, dp(10), 0));
        form.addView(actions, matchWrap());
        form.addView(status, spacedSmall());
        content.addView(form, sectionParams());
    }

    private LinearLayout indicatorRow(String label, String rawValue) {
        String value = nonEmpty(rawValue, "No detectado");
        int color = indicatorColor(value);
        return twoLineRow(label, value, "Indicador basado en evaluacion IA y senales operativas.", color);
    }

    private String detailTitle() {
        if ("evaluation".equals(detailType)) {
            return "Evaluacion";
        }
        if ("transcript".equals(detailType)) {
            return "Transcripcion";
        }
        if ("campaign".equals(detailType)) {
            return "Campana";
        }
        if ("form".equals(detailType)) {
            return "Ficha";
        }
        if ("insight".equals(detailType)) {
            return "Insight IA";
        }
        if ("alert".equals(detailType)) {
            return "Alerta";
        }
        return "Detalle";
    }

    private String detailSubtitle() {
        if (detailData == null) {
            return "Informacion movil";
        }
        if ("evaluation".equals(detailType)) {
            return nonEmpty(detailData.optString("campaign"), "Evaluacion de calidad") + " | " + nonEmpty(detailData.optString("status_label"), "Sin estado");
        }
        if ("transcript".equals(detailType)) {
            return nonEmpty(detailData.optString("file_name"), "Audio o interaccion");
        }
        if ("campaign".equals(detailType) || "form".equals(detailType)) {
            return nonEmpty(detailData.optString("name"), "Registro");
        }
        if ("insight".equals(detailType)) {
            return nonEmpty(detailData.optString("date_range"), "Reporte operativo");
        }
        if ("alert".equals(detailType)) {
            return nonEmpty(detailData.optString("title"), "Alerta operativa");
        }
        return "Informacion movil";
    }

    private void openDetail(String type, JSONObject data) {
        detailType = type;
        detailData = data;
        renderDashboard();
    }

    private void openEvaluationFromAlert(int evaluationId) {
        JSONObject evaluation = findEvaluation(evaluationId);
        if (evaluation != null) {
            openDetail("evaluation", evaluation);
        }
    }

    private JSONObject findEvaluation(int evaluationId) {
        if (evaluationId <= 0 || dashboardData == null) {
            return null;
        }
        JSONObject modules = object(dashboardData, "modules");
        JSONObject evaluations = object(modules, "evaluations");
        JSONObject agent = object(dashboardData, "agent");
        JSONArray[] lists = new JSONArray[]{
            dashboardData.optJSONArray("evaluations"),
            evaluations.optJSONArray("items"),
            agent.optJSONArray("match_history")
        };
        for (JSONArray list : lists) {
            if (list == null) {
                continue;
            }
            for (int i = 0; i < list.length(); i++) {
                JSONObject item = list.optJSONObject(i);
                if (item != null && item.optInt("id", 0) == evaluationId) {
                    return item;
                }
            }
        }
        return null;
    }

    private boolean canRespondFeedback(JSONObject evaluation, JSONObject response) {
        JSONObject profile = object(dashboardData, "profile");
        return "agent".equals(profile.optString("primary_view", "executive"))
            && "published_to_agent".equals(evaluation.optString("status"))
            && ! response.optBoolean("responded");
    }

    private void maybeMarkEvaluationViewed(JSONObject evaluation) {
        JSONObject profile = object(dashboardData, "profile");
        if (! "agent".equals(profile.optString("primary_view", "executive"))
            || ! "published_to_agent".equals(evaluation.optString("status"))
            || ! nonEmpty(evaluation.optString("agent_viewed_at"), "").isEmpty()) {
            return;
        }

        int id = evaluation.optInt("id", 0);
        if (id <= 0) {
            return;
        }

        executor.execute(() -> {
            try {
                JSONObject response = request("POST", "/api/mobile/evaluations/" + id + "/viewed", new JSONObject());
                JSONObject updated = response.optJSONObject("evaluation");
                if (updated != null) {
                    runOnMain(() -> {
                        if ("evaluation".equals(detailType) && detailData != null && detailData.optInt("id", 0) == id) {
                            detailData = updated;
                            renderDashboard();
                        }
                    });
                }
            } catch (Exception ignored) {
            }
        });
    }

    private void submitFeedback(int evaluationId, String type, String textValue, TextView status) {
        String value = textValue == null ? "" : textValue.trim();
        if (evaluationId <= 0) {
            status.setText("No se pudo identificar la evaluacion.");
            return;
        }
        if (value.isEmpty()) {
            status.setText("Agrega un comentario breve para registrar la respuesta.");
            return;
        }

        status.setText("Guardando respuesta...");
        executor.execute(() -> {
            try {
                JSONObject body = new JSONObject();
                body.put("response_type", type);
                if ("dispute".equals(type)) {
                    body.put("dispute_reason", value);
                } else {
                    body.put("commitment_comment", value);
                }

                JSONObject response = request("POST", "/api/mobile/evaluations/" + evaluationId + "/respond", body);
                JSONObject updated = response.optJSONObject("evaluation");
                runOnMain(() -> {
                    if (updated != null) {
                        detailData = updated;
                    }
                    renderDashboard();
                    loadDashboard();
                });
            } catch (Exception ex) {
                runOnMain(() -> status.setText(cleanError(ex)));
            }
        });
    }

    private void renderLoading() {
        if (content == null) {
            return;
        }
        content.removeAllViews();
        LinearLayout loading = card();
        loading.setGravity(Gravity.CENTER);
        loading.setPadding(dp(16), dp(34), dp(16), dp(34));
        ProgressBar progress = new ProgressBar(this);
        loading.addView(progress, wrapWrap());
        TextView text = text("Cargando informacion movil...", 14, muted(), Typeface.NORMAL);
        text.setGravity(Gravity.CENTER);
        loading.addView(text, spaced());
        content.addView(loading, matchWrap());
    }

    private void renderError(String message) {
        if (content == null) {
            return;
        }
        content.removeAllViews();
        LinearLayout error = card();
        error.setPadding(dp(16), dp(18), dp(16), dp(18));
        error.addView(text("No se pudo cargar la informacion", 18, textColor(), Typeface.BOLD), matchWrap());
        error.addView(bodyText(nonEmpty(message, "Error desconocido.")), spaced());
        Button retry = primaryButton("Reintentar");
        retry.setOnClickListener(v -> loadDashboard());
        error.addView(retry, spaced());
        content.addView(error, matchWrap());
    }

    private LinearLayout bottomNavigation() {
        LinearLayout wrapper = vertical();
        wrapper.setPadding(dp(14), dp(6), dp(14), dp(14));
        wrapper.setBackgroundColor(bg());

        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER);
        row.setPadding(dp(6), dp(6), dp(6), dp(6));
        row.setBackground(rounded(navBg(), border(), 30));
        row.setElevation(dp(10));
        row.addView(navButton("dashboard", "Inicio", R.drawable.ic_nav_home), new LinearLayout.LayoutParams(0, dp(62), 1));
        row.addView(navButton("transcripts", "Audios", R.drawable.ic_nav_audio), new LinearLayout.LayoutParams(0, dp(62), 1));
        row.addView(navButton("evaluations", "Eval.", R.drawable.ic_nav_eval), new LinearLayout.LayoutParams(0, dp(62), 1));
        row.addView(navButton("campaigns", "Camp.", R.drawable.ic_nav_campaign), new LinearLayout.LayoutParams(0, dp(62), 1));
        row.addView(navButton("more", "Mas", R.drawable.ic_nav_more), new LinearLayout.LayoutParams(0, dp(62), 1));
        wrapper.addView(row, matchWrap());
        return wrapper;
    }

    private LinearLayout navButton(String key, String label, int iconRes) {
        boolean active = key.equals(activeTab);

        LinearLayout item = vertical();
        item.setGravity(Gravity.CENTER);
        item.setPadding(dp(2), 0, dp(2), 0);

        LinearLayout pill = vertical();
        pill.setGravity(Gravity.CENTER);
        pill.setPadding(dp(8), dp(5), dp(8), dp(5));
        pill.setBackground(rounded(active ? accent() : Color.TRANSPARENT, active ? accent() : Color.TRANSPARENT, active ? 22 : 18));

        ImageView icon = new ImageView(this);
        icon.setImageResource(iconRes);
        icon.setColorFilter(active ? Color.WHITE : muted());
        pill.addView(icon, new LinearLayout.LayoutParams(dp(22), dp(22)));

        TextView text = text(label, 10, active ? Color.WHITE : muted(), active ? Typeface.BOLD : Typeface.NORMAL);
        text.setGravity(Gravity.CENTER);
        text.setSingleLine(true);
        text.setEllipsize(TextUtils.TruncateAt.END);
        pill.addView(text, spacedSmall());
        item.addView(pill, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, LinearLayout.LayoutParams.WRAP_CONTENT));

        item.setOnClickListener(v -> {
            activeTab = key;
            detailType = "";
            detailData = null;
            if (dashboardData == null) {
                renderLoading();
            } else {
                renderDashboard();
            }
        });
        return item;
    }

    private JSONObject request(String method, String path, JSONObject body) throws Exception {
        URL url = new URL(serverUrl + path);
        HttpURLConnection connection = (HttpURLConnection) url.openConnection();
        connection.setRequestMethod(method);
        connection.setConnectTimeout(15000);
        connection.setReadTimeout(20000);
        connection.setRequestProperty("Accept", "application/json");

        if (token != null && !token.isEmpty()) {
            connection.setRequestProperty("Authorization", "Bearer " + token);
        }

        if (body != null) {
            byte[] bytes = body.toString().getBytes(StandardCharsets.UTF_8);
            connection.setDoOutput(true);
            connection.setRequestProperty("Content-Type", "application/json; charset=utf-8");
            connection.setFixedLengthStreamingMode(bytes.length);
            try (OutputStream output = connection.getOutputStream()) {
                output.write(bytes);
            }
        }

        int code = connection.getResponseCode();
        BufferedReader reader = new BufferedReader(new InputStreamReader(
            code >= 200 && code < 300 ? connection.getInputStream() : connection.getErrorStream(),
            StandardCharsets.UTF_8
        ));
        StringBuilder response = new StringBuilder();
        String line;
        while ((line = reader.readLine()) != null) {
            response.append(line);
        }
        reader.close();
        connection.disconnect();

        JSONObject json = response.length() == 0 ? new JSONObject() : new JSONObject(response.toString());
        if (code < 200 || code >= 300) {
            throw new ApiException(code, json.optString("message", "Error del servidor"));
        }

        return json;
    }

    private void logout() {
        executor.execute(() -> {
            try {
                request("POST", "/api/mobile/logout", new JSONObject());
            } catch (Exception ignored) {
            }
            clearSession();
            runOnMain(this::showLogin);
        });
    }

    private LinearLayout metricRow(View left, View right) {
        LinearLayout row = horizontal();
        row.addView(left, weightParams(1, 0, dp(5), dp(8), 0));
        row.addView(right, weightParams(1, dp(5), 0, dp(8), 0));
        return row;
    }

    private LinearLayout metricCard(String label, String value, String detail, int color) {
        LinearLayout box = compactCard();
        box.addView(text(label, 12, muted(), Typeface.BOLD), matchWrap());
        box.addView(text(value, 24, color, Typeface.BOLD), spacedSmall());
        TextView body = text(detail, 11, muted(), Typeface.NORMAL);
        body.setMaxLines(2);
        box.addView(body, spacedSmall());
        return box;
    }

    private LinearLayout compactMetric(String label, String value, int color) {
        LinearLayout box = compactCard();
        box.addView(text(label, 11, muted(), Typeface.BOLD), matchWrap());
        box.addView(text(value, 23, color, Typeface.BOLD), spacedSmall());
        return box;
    }

    private LinearLayout progressLine(String label, double percent, int color) {
        LinearLayout box = vertical();
        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);
        TextView labelView = text(label, 13, textColor(), Typeface.BOLD);
        labelView.setSingleLine(true);
        labelView.setEllipsize(TextUtils.TruncateAt.END);
        row.addView(labelView, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
        row.addView(text(percentText(percent), 13, color, Typeface.BOLD), wrapWrap());
        box.addView(row, matchWrap());

        LinearLayout track = new LinearLayout(this);
        track.setBackground(rounded(trackColor(), trackColor(), 10));
        LinearLayout fill = new LinearLayout(this);
        fill.setBackground(rounded(color, color, 10));
        int width = Math.max(0, Math.min(100, (int) Math.round(percent)));
        track.addView(fill, new LinearLayout.LayoutParams(0, dp(8), width));
        track.addView(new LinearLayout(this), new LinearLayout.LayoutParams(0, dp(8), 100 - width));
        LinearLayout.LayoutParams trackParams = matchWrap();
        trackParams.setMargins(0, dp(7), 0, 0);
        box.addView(track, trackParams);
        return box;
    }

    private LinearLayout twoLineRow(String title, String value, String detail, int color) {
        LinearLayout row = compactCard();
        row.addView(rowTitleValue(title, value, color), matchWrap());
        row.addView(text(detail, 12, muted(), Typeface.NORMAL), spacedSmall());
        return row;
    }

    private LinearLayout rowTitleValue(String title, String value, int color) {
        LinearLayout top = horizontal();
        top.setGravity(Gravity.CENTER_VERTICAL);
        TextView titleView = text(title, 14, textColor(), Typeface.BOLD);
        titleView.setSingleLine(true);
        titleView.setEllipsize(TextUtils.TruncateAt.END);
        top.addView(titleView, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
        top.addView(labelChip(value, color), wrapWrap());
        return top;
    }

    private LinearLayout chipRow(String[] values) {
        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);
        for (String value : values) {
            TextView chip = labelChip(value, muted());
            LinearLayout.LayoutParams params = wrapWrap();
            params.setMargins(0, 0, dp(6), 0);
            row.addView(chip, params);
        }
        return row;
    }

    private LinearLayout detailCard(String type, JSONObject data) {
        LinearLayout item = compactCard();
        if (data != null) {
            item.setOnClickListener(v -> openDetail(type, data));
        }
        return item;
    }

    private LinearLayout infoRow(String label, String value) {
        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);
        row.addView(text(label, 13, muted(), Typeface.NORMAL), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
        row.addView(text(value, 13, textColor(), Typeface.BOLD), wrapWrap());
        return row;
    }

    private TextView bodyText(String value) {
        TextView view = text(value, 12, muted(), Typeface.NORMAL);
        view.setMaxLines(3);
        view.setEllipsize(TextUtils.TruncateAt.END);
        return view;
    }

    private TextView emptyText(String value) {
        TextView view = text(value, 13, muted(), Typeface.NORMAL);
        view.setGravity(Gravity.CENTER);
        view.setPadding(0, dp(10), 0, dp(10));
        return view;
    }

    private TextView labelChip(String label, int color) {
        TextView chip = text(label, 11, color, Typeface.BOLD);
        chip.setSingleLine(true);
        chip.setEllipsize(TextUtils.TruncateAt.END);
        chip.setPadding(dp(8), dp(4), dp(8), dp(4));
        chip.setBackground(rounded(alpha(color, darkMode ? 38 : 24), alpha(color, 100), 18));
        return chip;
    }

    private LinearLayout section(String titleText) {
        LinearLayout section = card();
        section.setPadding(dp(14), dp(14), dp(14), dp(14));
        section.addView(text(titleText, 17, textColor(), Typeface.BOLD), matchWrap());
        return section;
    }

    private LinearLayout card() {
        LinearLayout layout = vertical();
        layout.setBackground(rounded(cardColor(), border(), 8));
        return layout;
    }

    private LinearLayout compactCard() {
        LinearLayout layout = vertical();
        layout.setPadding(dp(12), dp(12), dp(12), dp(12));
        layout.setBackground(rounded(cardAlt(), border(), 8));
        return layout;
    }

    private EditText input(String hint) {
        EditText input = new EditText(this);
        input.setTextColor(textColor());
        input.setHintTextColor(muted());
        input.setTextSize(15);
        input.setSingleLine(true);
        input.setHint(hint);
        input.setPadding(dp(12), 0, dp(12), 0);
        input.setBackground(rounded(inputBg(), border(), 8));
        input.setMinHeight(dp(50));
        return input;
    }

    private EditText multiLineInput(String hint) {
        EditText input = input(hint);
        input.setSingleLine(false);
        input.setMinLines(3);
        input.setGravity(Gravity.TOP);
        input.setPadding(dp(12), dp(10), dp(12), dp(10));
        return input;
    }

    private PasswordField passwordField(String hint) {
        LinearLayout container = horizontal();
        container.setGravity(Gravity.CENTER_VERTICAL);
        container.setPadding(dp(12), 0, dp(4), 0);
        container.setMinimumHeight(dp(50));
        container.setBackground(rounded(inputBg(), border(), 8));

        EditText input = new EditText(this);
        input.setTextColor(textColor());
        input.setHintTextColor(muted());
        input.setTextSize(15);
        input.setSingleLine(true);
        input.setHint(hint);
        input.setPadding(0, 0, dp(8), 0);
        input.setBackgroundColor(Color.TRANSPARENT);
        input.setTransformationMethod(PasswordTransformationMethod.getInstance());
        container.addView(input, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.MATCH_PARENT, 1));

        ImageButton toggle = new ImageButton(this);
        toggle.setImageResource(R.drawable.ic_visibility);
        toggle.setBackground(rounded(Color.TRANSPARENT, Color.TRANSPARENT, 8));
        toggle.setColorFilter(muted());
        toggle.setContentDescription("Mostrar contrasena");
        toggle.setOnClickListener(v -> {
            boolean hidden = input.getTransformationMethod() instanceof PasswordTransformationMethod;
            input.setTransformationMethod(hidden ? HideReturnsTransformationMethod.getInstance() : PasswordTransformationMethod.getInstance());
            toggle.setImageResource(hidden ? R.drawable.ic_visibility_off : R.drawable.ic_visibility);
            toggle.setContentDescription(hidden ? "Ocultar contrasena" : "Mostrar contrasena");
            input.setSelection(input.getText().length());
        });
        container.addView(toggle, new LinearLayout.LayoutParams(dp(42), dp(42)));
        return new PasswordField(container, input);
    }

    private Button primaryButton(String label) {
        Button button = new Button(this);
        button.setText(label);
        button.setTextColor(Color.WHITE);
        button.setTextSize(14);
        button.setTypeface(Typeface.DEFAULT, Typeface.BOLD);
        button.setAllCaps(false);
        button.setBackground(rounded(accent(), accent(), 8));
        button.setMinHeight(dp(50));
        return button;
    }

    private Button smallButton(String label) {
        Button button = new Button(this);
        button.setText(label);
        button.setTextColor(textColor());
        button.setTextSize(11);
        button.setAllCaps(false);
        button.setBackground(rounded(cardAlt(), border(), 8));
        button.setMinHeight(dp(36));
        button.setPadding(dp(10), 0, dp(10), 0);
        return button;
    }

    private ImageButton themeIconButton() {
        return headerIconButton(darkMode ? R.drawable.ic_sun : R.drawable.ic_moon, "Cambiar tema", v -> {
            darkMode = !darkMode;
            prefs.edit().putBoolean("dark_mode", darkMode).apply();
            if (token == null || token.isEmpty()) {
                showLogin();
            } else if (dashboardData == null) {
                showDashboard();
            } else {
                renderDashboard();
            }
        });
    }

    private ImageButton headerIconButton(int icon, String label, View.OnClickListener listener) {
        ImageButton button = new ImageButton(this);
        button.setImageResource(icon);
        button.setColorFilter(muted());
        button.setBackground(rounded(cardAlt(), border(), 8));
        button.setContentDescription(label);
        button.setPadding(dp(10), dp(10), dp(10), dp(10));
        button.setOnClickListener(listener);
        return button;
    }

    private ImageView logoView(int widthDp, int heightDp) {
        ImageView logo = new ImageView(this);
        logo.setImageResource(R.drawable.qa_logo);
        logo.setAdjustViewBounds(true);
        logo.setScaleType(ImageView.ScaleType.FIT_CENTER);
        logo.setMaxWidth(dp(widthDp));
        logo.setMaxHeight(dp(heightDp));
        return logo;
    }

    private ImageView avatarView(JSONObject profile, int sizeDp) {
        ImageView avatar = new ImageView(this);
        avatar.setImageResource(R.drawable.qa_logo);
        avatar.setScaleType(ImageView.ScaleType.CENTER_CROP);
        avatar.setPadding(dp(5), dp(5), dp(5), dp(5));
        avatar.setBackground(rounded(alpha(accent(), 32), alpha(accent(), 120), sizeDp));
        String avatarUrl = profile == null ? "" : profile.optString("avatar_url", "");
        if (avatarUrl != null && !avatarUrl.trim().isEmpty()) {
            loadImage(avatar, avatarUrl);
        }
        return avatar;
    }

    private void loadImage(ImageView target, String url) {
        executor.execute(() -> {
            try {
                HttpURLConnection connection = (HttpURLConnection) new URL(url).openConnection();
                connection.setConnectTimeout(8000);
                connection.setReadTimeout(10000);
                connection.setRequestProperty("Accept", "image/*");
                try (InputStream stream = connection.getInputStream()) {
                    Bitmap bitmap = BitmapFactory.decodeStream(stream);
                    if (bitmap != null) {
                        runOnMain(() -> {
                            target.setPadding(0, 0, 0, 0);
                            target.setImageBitmap(bitmap);
                        });
                    }
                }
                connection.disconnect();
            } catch (Exception ignored) {
            }
        });
    }

    private TextView text(String value, int sp, int color, int style) {
        TextView view = new TextView(this);
        view.setText(value);
        view.setTextSize(sp);
        view.setTextColor(color);
        view.setTypeface(Typeface.DEFAULT, style);
        view.setLineSpacing(0, 1.08f);
        return view;
    }

    private LinearLayout vertical() {
        LinearLayout layout = new LinearLayout(this);
        layout.setOrientation(LinearLayout.VERTICAL);
        return layout;
    }

    private LinearLayout horizontal() {
        LinearLayout layout = new LinearLayout(this);
        layout.setOrientation(LinearLayout.HORIZONTAL);
        return layout;
    }

    private GradientDrawable rounded(int fill, int stroke, int radiusDp) {
        GradientDrawable drawable = new GradientDrawable();
        drawable.setColor(fill);
        drawable.setCornerRadius(dp(radiusDp));
        drawable.setStroke(dp(1), stroke);
        return drawable;
    }

    private LinearLayout.LayoutParams matchWrap() {
        return new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, LinearLayout.LayoutParams.WRAP_CONTENT);
    }

    private LinearLayout.LayoutParams wrapWrap() {
        return new LinearLayout.LayoutParams(LinearLayout.LayoutParams.WRAP_CONTENT, LinearLayout.LayoutParams.WRAP_CONTENT);
    }

    private LinearLayout.LayoutParams sectionParams() {
        LinearLayout.LayoutParams params = matchWrap();
        params.setMargins(0, dp(12), 0, 0);
        return params;
    }

    private LinearLayout.LayoutParams spaced() {
        LinearLayout.LayoutParams params = matchWrap();
        params.setMargins(0, dp(10), 0, 0);
        return params;
    }

    private LinearLayout.LayoutParams spacedSmall() {
        LinearLayout.LayoutParams params = matchWrap();
        params.setMargins(0, dp(5), 0, 0);
        return params;
    }

    private LinearLayout.LayoutParams weightParams(float weight, int left, int right, int top, int bottom) {
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, weight);
        params.setMargins(left, top, right, bottom);
        return params;
    }

    private LinearLayout.LayoutParams marginLeft(int left, int width, int height) {
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(width, height);
        params.setMargins(left, 0, 0, 0);
        return params;
    }

    private JSONObject object(JSONObject parent, String key) {
        JSONObject object = parent == null ? null : parent.optJSONObject(key);
        return object == null ? new JSONObject() : object;
    }

    private String percentText(Object value) {
        if (value == null || JSONObject.NULL.equals(value)) {
            return "0%";
        }
        try {
            double number = value instanceof Number ? ((Number) value).doubleValue() : Double.parseDouble(String.valueOf(value));
            return percentText(number);
        } catch (Exception ignored) {
            return nonEmpty(String.valueOf(value), "0%");
        }
    }

    private String percentText(double value) {
        return String.format(Locale.US, "%.1f%%", value);
    }

    private String shortLabel(String value) {
        if (value == null) {
            return "Dia";
        }
        return value.length() > 10 ? value.substring(value.length() - 5) : value;
    }

    private String roleLabel(String role) {
        if (role == null || role.trim().isEmpty()) {
            return "Centro movil";
        }
        return role.replace('_', ' ');
    }

    private int scoreColor(double score) {
        if (score < 0) {
            return muted();
        }
        if (score < 70) {
            return ROSE;
        }
        if (score < 85) {
            return AMBER;
        }
        return GREEN;
    }

    private int severityColor(String severity) {
        if ("critical".equalsIgnoreCase(severity)) {
            return ROSE;
        }
        if ("warning".equalsIgnoreCase(severity)) {
            return AMBER;
        }
        return CYAN;
    }

    private int indicatorColor(String value) {
        String normalized = value == null ? "" : value.toLowerCase(Locale.US);
        if (normalized.isEmpty() || normalized.contains("no detectado")) {
            return muted();
        }
        if (normalized.contains("alto")
            || normalized.contains("riesgo")
            || normalized.contains("critico")
            || normalized.contains("sin resolver")) {
            return ROSE;
        }
        if (normalized.contains("medio")
            || normalized.contains("neutral")
            || normalized.contains("pendiente")) {
            return AMBER;
        }
        if (normalized.contains("fortaleza")
            || normalized.contains("si")
            || normalized.contains("claro")
            || normalized.contains("detectado")) {
            return GREEN;
        }
        return muted();
    }

    private String booleanLabel(Object value) {
        if (value == null || JSONObject.NULL.equals(value)) {
            return "No detectado";
        }
        if (value instanceof Boolean) {
            return (Boolean) value ? "Si" : "No";
        }
        String normalized = String.valueOf(value).trim();
        if (normalized.isEmpty()) {
            return "No detectado";
        }
        if ("1".equals(normalized) || "true".equalsIgnoreCase(normalized)) {
            return "Si";
        }
        if ("0".equals(normalized) || "false".equalsIgnoreCase(normalized)) {
            return "No";
        }
        return normalized;
    }

    private String formatDurationFromJson(Object value) {
        if (value == null || JSONObject.NULL.equals(value)) {
            return "00:00";
        }
        try {
            double seconds = value instanceof Number ? ((Number) value).doubleValue() : Double.parseDouble(String.valueOf(value));
            int total = Math.max(0, (int) Math.round(seconds));
            return String.format(Locale.US, "%02d:%02d", total / 60, total % 60);
        } catch (Exception ignored) {
            return "00:00";
        }
    }

    private int bg() {
        return darkMode ? Color.rgb(9, 10, 12) : Color.rgb(246, 247, 250);
    }

    private int navBg() {
        return darkMode ? Color.rgb(13, 14, 17) : Color.WHITE;
    }

    private int cardColor() {
        return darkMode ? Color.rgb(19, 20, 24) : Color.WHITE;
    }

    private int cardAlt() {
        return darkMode ? Color.rgb(25, 27, 32) : Color.rgb(250, 251, 253);
    }

    private int inputBg() {
        return darkMode ? Color.rgb(25, 27, 32) : Color.WHITE;
    }

    private int trackColor() {
        return darkMode ? Color.rgb(43, 45, 52) : Color.rgb(229, 231, 235);
    }

    private int border() {
        return darkMode ? Color.rgb(47, 50, 58) : Color.rgb(224, 228, 236);
    }

    private int textColor() {
        return darkMode ? Color.rgb(245, 247, 250) : Color.rgb(17, 24, 39);
    }

    private int muted() {
        return darkMode ? Color.rgb(161, 166, 179) : Color.rgb(99, 107, 124);
    }

    private int accent() {
        return darkMode ? GREEN : BLUE;
    }

    private int alpha(int color, int alpha) {
        return Color.argb(alpha, Color.red(color), Color.green(color), Color.blue(color));
    }

    private void applySystemBars() {
        getWindow().setStatusBarColor(bg());
        getWindow().setNavigationBarColor(navBg());
    }

    private int dp(int value) {
        return (int) (value * getResources().getDisplayMetrics().density + 0.5f);
    }

    private void clearSession() {
        token = null;
        dashboardData = null;
        detailData = null;
        detailType = "";
        prefs.edit().remove("token").apply();
    }

    private void runOnMain(Runnable runnable) {
        mainHandler.post(runnable);
    }

    private String normalizeServer(String value) {
        String normalized = value == null ? "" : value.trim();
        if (normalized.isEmpty()) {
            return "";
        }
        if (!normalized.startsWith("http://") && !normalized.startsWith("https://")) {
            normalized = "https://" + normalized;
        }
        while (normalized.endsWith("/")) {
            normalized = normalized.substring(0, normalized.length() - 1);
        }
        return normalized;
    }

    private String nonEmpty(String value, String fallback) {
        return value == null || value.trim().isEmpty() || "null".equalsIgnoreCase(value.trim()) ? fallback : value;
    }

    private String cleanError(Exception ex) {
        String message = ex.getMessage();
        if (message == null || message.trim().isEmpty()) {
            return "No se pudo completar la solicitud.";
        }
        return message.replace("java.lang.Exception:", "").trim();
    }

    private static class PasswordField {
        final LinearLayout container;
        final EditText input;

        PasswordField(LinearLayout container, EditText input) {
            this.container = container;
            this.input = input;
        }
    }

    private static class ApiException extends Exception {
        final int code;

        ApiException(int code, String message) {
            super(message);
            this.code = code;
        }
    }
}
