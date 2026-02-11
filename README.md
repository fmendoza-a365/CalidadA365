# QA365 - Plataforma de Gestión de Calidad (QM) con IA

Sistema integral de aseguramiento de calidad para Contact Centers, potenciado por Inteligencia Artificial para la evaluación automática de transcripciones, gestión de disputas y análisis de desempeño.

---

## 🚀 Características Principales

*   **Evaluación con IA**: Análisis automático de texto y audio utilizando LLMs (Gemini, OpenAI, Claude).
*   **Gestión de Campañas**: Administración completa de campañas con asignación de agentes y supervisores.
*   **Fichas de Calidad Dinámicas**: Creación de formularios de evaluación con versiones, atributos y pesos ponderados.
*   **Dashboards por Rol**: Vistas personalizadas para Administradores, QA Managers, Supervisores y Agentes.
*   **Flujo de Disputas**: Sistema para que los agentes acepten o refuten evaluaciones, con resolución por parte de QA.
*   **Interfaz Moderna**: Diseño UI/UX profesional con modo oscuro, glassmorphism y animaciones fluidas (Tailwind CSS + Alpine.js).
*   **Escalable**: Arquitectura basada en colas (Redis) y almacenamiento en la nube (S3 compatible) lista para producción.

---

## 🛠️ Stack Tecnológico

*   **Backend**: Laravel 11 (PHP 8.3)
*   **Base de Datos**: PostgreSQL (Producción) / SQLite (Desarrollo)
*   **Frontend**: Blade, Alpine.js, Tailwind CSS
*   **Cola de Trabajos**: Redis
*   **IA**: Integración con Google Gemini, OpenAI y Anthropic Claude via API.

---

## 📦 Instalación Local

### Prerrequisitos
*   PHP 8.2+
*   Composer
*   Node.js & NPM
*   Redis (Opcional para desarrollo, requerido para producción)

### Pasos

1.  **Clonar el repositorio**
    ```bash
    git clone <url-del-repositorio>
    cd QA365
    ```

2.  **Instalar dependencias**
    ```bash
    composer install
    npm install
    ```

3.  **Configurar entorno**
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```
    *   Configura tu base de datos en `.env`.
    *   Si no tienes Redis local, cambia `QUEUE_CONNECTION=sync` en `.env`.

4.  **Base de Datos y Datos Semilla**
    ```bash
    php artisan migrate
    php artisan db:seed --class=RoleSeeder
    php artisan db:seed --class=UserSeeder
    php artisan db:seed --class=DemoDataSeeder
    ```

5.  **Ejecutar**
    ```bash
    npm run dev    # Compila assets en tiempo real
    php artisan serve  # Inicia el servidor
    ```

    Accede a: `http://localhost:8000`

---

## 🔑 Credenciales de Demo

El `DemoDataSeeder` crea los siguientes usuarios para probar cada rol:

| Rol | Email | Contraseña |
| :--- | :--- | :--- |
| **Admin** | `admin@qa.com` | `password` |
| **QA Manager** | `qa@qa.com` | `password` |
| **Supervisor** | `supervisor@qa.com` | `password` |
| **Agente** | `agent@qa.com` | `password` |

---

## ☁️ Despliegue (Render / Railway)

El proyecto incluye configuración lista para despliegue:

1.  **Dockerfile**: Para construir la imagen de producción.
2.  **render.yaml**: Blueprint para despliegue automático en Render.
3.  **railway-setup.sh**: Script de ayuda para Railway.

### Variables de Entorno Críticas
*   `APP_ENV`: `production`
*   `APP_KEY`: Genérala con `php artisan key:generate`
*   `DATABASE_URL`: URL de conexión a PostgreSQL.
*   `REDIS_URL`: URL de conexión a Redis (necesario para colas).
*   `AI_PROVIDER`: `gemini`, `openai` o `claude`.
*   `AI_GEMINI_API_KEY`: Tu llave de API.

---

## 📂 Estructura del Proyecto

*   `app/Models`: Modelos Eloquent (Campaign, QualityForm, Interaction, Evaluation).
*   `app/Http/Controllers`: Lógica de negocio y gestión de peticiones.
*   `app/Services`: Lógica compleja (AIEvaluationService, ScoreCalculator).
*   `app/Jobs`: Procesos en segundo plano (ScoreTranscriptJob, TranscribeAudioJob).
*   `resources/views`: Plantillas Blade organizadas por módulos.
*   `routes/web.php`: Definición de rutas y middleware.

---

## 📝 Créditos y Licencia

**Derechos reservados:** "Bearlytics" (Analistas de Lalo).

Desarrollado con ❤️ por **Impulsa365**.

Para soporte y contacto:
*   **Impulsa365**: Contact Center Solutions
*   **Bearlytics**: Analítica Avanzada
