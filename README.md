# 📞 Sistema QA/QM Call Center

Sistema de evaluación de calidad para call centers con evaluación automática mediante IA. Procesa transcripciones de llamadas en formato `.txt` y genera evaluaciones detalladas con evidencias extraídas del texto.

![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/Tailwind-3.0-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)

## ✨ Características Principales

### 🎯 Gestión de Calidad
- **Fichas de Calidad Versionadas**: Crea y gestiona formularios de evaluación con atributos y subatributos ponderados
- **Evaluación Automática con IA**: Análisis inteligente de transcripciones con extracción de evidencias
- **Cálculo de Puntajes Ponderados**: Sistema automático de scoring basado en pesos configurables
- **Flujo de Respuestas**: Los asesores pueden aceptar o refutar evaluaciones
- **Resolución de Disputas**: QA Managers pueden revisar y resolver apelaciones

### 👥 Roles y Permisos
- **Admin**: Acceso completo al sistema
- **QA Manager**: Gestión de fichas, resolución de disputas, reportes
- **Supervisor**: Visualización de desempeño del equipo
- **Asesor**: Acceso a evaluaciones personales y respuestas

### 📊 Dashboards Personalizados
- Dashboard Administrativo con métricas globales
- Dashboard de Supervisor con desempeño del equipo
- Dashboard de Asesor con evaluaciones personales

### 🎨 Interfaz Moderna
- Diseño con **Glassmorphism** y gradientes
- **Dark Mode** completo
- Animaciones suaves y transiciones
- Componentes reutilizables con TailwindCSS
- Responsive design (mobile-first)

## 🚀 Instalación

### Requisitos Previos
- PHP 8.2 o superior
- Composer
- Node.js y NPM
- MySQL 8.0 o superior
- Redis (opcional, para queues)

### Paso 1: Clonar el Repositorio
```bash
git clone <repository-url>
cd CalidadProyecto
```

### Paso 2: Instalar Dependencias
```bash
# Dependencias de PHP
composer install

# Dependencias de Node.js
npm install
```

### Paso 3: Configurar Entorno
```bash
# Copiar archivo de configuración
cp .env.example .env

# Generar clave de aplicación
php artisan key:generate
```

### Paso 4: Configurar Base de Datos
Edita el archivo `.env` con tus credenciales de MySQL:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=calidad_qa
DB_USERNAME=root
DB_PASSWORD=tu_password
```

### Paso 5: Ejecutar Migraciones y Seeders
```bash
# Crear tablas
php artisan migrate

# Cargar roles y usuarios de prueba
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=UserSeeder

# (Opcional) Cargar datos de demostración
php artisan db:seed --class=DemoDataSeeder
```

### Paso 6: Compilar Assets
```bash
# Desarrollo
npm run dev

# Producción
npm run build
```

### Paso 7: Iniciar Servidor
```bash
php artisan serve
```

La aplicación estará disponible en: **http://localhost:8000**

## 👤 Usuarios de Prueba

| Rol | Email | Password |
|-----|-------|----------|
| Admin | admin@qa.com | password |
| QA Manager | qa@qa.com | password |
| Supervisor | supervisor@qa.com | password |
| Asesor | agent@qa.com | password |

## 📖 Uso del Sistema

### 1. Crear una Campaña
1. Login como Admin o QA Manager
2. Ir a **Campañas** → **Nueva Campaña**
3. Completar nombre y descripción
4. Guardar

### 2. Crear Ficha de Calidad
1. Ir a **Fichas de Calidad** (próximamente)
2. Crear atributos con sus pesos
3. Agregar subatributos (deben sumar 100% por atributo)
4. Publicar la ficha
5. Asignar a la campaña

### 3. Asignar Asesores
1. En la campaña, ir a **Asignaciones**
2. Seleccionar asesor y supervisor
3. Definir fechas de vigencia
4. Guardar

### 4. Cargar Transcripciones
1. Ir a **Transcripciones** → **Cargar Transcripción**
2. Seleccionar campaña y asesor
3. Subir archivo(s) `.txt` (máx 50 archivos, 5MB c/u)
4. El sistema automáticamente:
   - Guarda la transcripción
   - Encola el job de evaluación
   - Procesa con IA
   - Calcula puntajes
   - Hace visible al asesor

### 5. Procesar Evaluaciones (Queue)
```bash
# Iniciar worker de cola
php artisan queue:work

# O usar sync para desarrollo (en .env)
QUEUE_CONNECTION=sync
```

### 6. Ver y Responder Evaluaciones
1. Login como asesor (agent@qa.com)
2. Ver evaluaciones en Dashboard o menú **Evaluaciones**
3. Revisar puntaje y evidencias
4. Aceptar con compromiso o Refutar con motivo

### 7. Resolver Disputas
1. Login como QA Manager
2. Ir a evaluación disputada
3. Revisar motivo del asesor
4. Decidir: Mantener, Anular o Ajustar
5. Agregar notas de resolución

## 🎨 Sistema de Diseño

### Clases CSS Personalizadas

#### Cards
```html
<div class="glass-card">Contenido</div>
<div class="glass-card-hover">Con efecto hover</div>
<div class="stat-card">Card de estadística</div>
```

#### Badges
```html
<span class="badge badge-success">Cumple</span>
<span class="badge badge-danger">No Cumple</span>
<span class="badge badge-warning">Pendiente</span>
```

#### Botones
```html
<button class="btn-primary">Primario</button>
<button class="btn-success">Éxito</button>
<button class="btn-danger">Peligro</button>
```

#### Scores
```html
<div class="score-excellent">95%</div>
<div class="score-good">85%</div>
<div class="score-average">75%</div>
<div class="score-poor">60%</div>
```

## 🔧 Configuración Avanzada

### Integración con IA Real

El sistema actualmente usa una simulación de IA basada en keywords. Para integrar OpenAI o Claude:

1. Instalar SDK:
```bash
composer require openai-php/client
```

2. Configurar en `.env`:
```env
AI_PROVIDER=openai
OPENAI_API_KEY=tu_api_key
OPENAI_MODEL=gpt-4
```

3. Actualizar `app/Jobs/ScoreTranscriptJob.php`:
```php
private function callAI($transcript, $formVersion)
{
    $client = OpenAI::client(config('services.openai.key'));
    
    $response = $client->chat()->create([
        'model' => 'gpt-4',
        'messages' => [
            ['role' => 'system', 'content' => $this->buildSystemPrompt($formVersion)],
            ['role' => 'user', 'content' => $transcript],
        ],
    ]);
    
    return json_decode($response->choices[0]->message->content, true);
}
```

### Configurar Redis Queue

1. Instalar Redis
2. Configurar en `.env`:
```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

3. Iniciar workers:
```bash
php artisan queue:work --tries=3
```

### Configurar Storage en S3

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=tu_key
AWS_SECRET_ACCESS_KEY=tu_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=tu_bucket
```

## 📁 Estructura del Proyecto

```
CalidadProyecto/
├── app/
│   ├── Http/Controllers/
│   │   ├── DashboardController.php
│   │   ├── CampaignController.php
│   │   ├── TranscriptController.php
│   │   ├── EvaluationController.php
│   │   └── AgentResponseController.php
│   ├── Models/
│   │   ├── Campaign.php
│   │   ├── QualityFormVersion.php
│   │   ├── Interaction.php
│   │   ├── Evaluation.php
│   │   └── ...
│   ├── Jobs/
│   │   └── ScoreTranscriptJob.php
│   ├── Services/
│   │   └── ScoreCalculator.php
│   └── Policies/
│       ├── EvaluationPolicy.php
│       └── DisputeResolutionPolicy.php
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/
│   ├── views/
│   │   ├── dashboard/
│   │   ├── campaigns/
│   │   ├── transcripts/
│   │   └── evaluations/
│   └── css/
│       └── app.css (Sistema de diseño)
└── routes/
    └── web.php
```

## 🧪 Testing

```bash
# Ejecutar tests
php artisan test

# Con coverage
php artisan test --coverage
```

## 📝 Roadmap

### MVP (Actual) ✅
- [x] Gestión de campañas
- [x] Carga de transcripciones
- [x] Evaluación automática (simulada)
- [x] Dashboards por rol
- [x] Respuestas y disputas
- [x] Sistema de diseño moderno

### v1.0 (Próximo)
- [ ] CRUD completo de Fichas de Calidad
- [ ] Integración real con OpenAI/Claude
- [ ] Informes semanales automáticos
- [ ] Exportación a Excel/PDF
- [ ] Notificaciones por email

### v1.5
- [ ] API REST completa
- [ ] Gráficos y analytics avanzados
- [ ] Módulo de coaching
- [ ] Comparativas y benchmarks

### v2.0
- [ ] Multi-tenancy
- [ ] Procesamiento de audio (speech-to-text)
- [ ] IA personalizada por campaña
- [ ] Mobile app

## 🤝 Contribución

Las contribuciones son bienvenidas. Por favor:
1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📄 Licencia

Este proyecto es privado y confidencial.

## 👨‍💻 Autor

Desarrollado con ❤️ para optimizar la calidad en call centers

## 📞 Soporte

Para soporte y consultas, contactar a: [tu-email@ejemplo.com]

---

**¡Gracias por usar el Sistema QA/QM Call Center!** 🎉
