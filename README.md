# QA365 - Global Quality Assurance Center v1.0
> **Bearlytics Ecosystem | Lalo's Analysts**

Sistema integral de aseguramiento de calidad potenciado por **Inteligencia Artificial Multimodal**, diseñado para escalar la supervisión al 100% de las operaciones en Contact Centers y unidades de atención al cliente.

---

## 💎 Valor Estratégico
QA365 no es solo un monitor de llamadas; es un motor de decisión asíncrono que transforma audio y texto en hallazgos accionables.
- **Escalabilidad Total:** Procesa miles de auditorías simultáneamente sin intervención humana.
- **Determinismo Empresarial:** Configuración de precisión (Temp 0.0, Top-P 0.1) para resultados consistentes y auditables.
- **Cierre de Brechas:** Gestión integrada de disputas y feedback directo al agente para mejora continua.

## 🚀 Documentación Maestra (Premium)
Para un análisis profundo del sistema, consulta nuestra guía técnico-estratégica:
👉 **[Abrir Manual Ejecutivo & Técnico](public/docs-executive.html)**

---

## 🧠 Características de la IA
Nuestro motor de evaluación (`AIEvaluationService`) utiliza arquitecturas de vanguardia para garantizar la precisión:
- **Multimodalidad:** Procesamiento directo de audio y transcripciones mediante **Gemini 2.0 Flash**.
- **Few-Shot Learning:** Inyección dinámica de **Golden Records** para alineación con el criterio de la empresa.
- **Chain of Thought:** Uso de hasta 65k tokens de salida para razonamiento antes de emitir puntuación.
- **Knockout Logic:** Sistema de fallas críticas que anulan el puntaje en caso de infracciones graves.

---

## 🛠️ Stack Tecnológico
- **Core:** Laravel 12 (PHP 8.3+)
- **Base de Datos:** PostgreSQL (Optimizado para análisis)
- **Caché & Colas:** Redis 7 (Gestión de carga asíncrona)
- **Frontend:** Blade, Alpine.js, Tailwind CSS 4.0 (Glassmorphism & UX Premium)
- **Infraestructura:** Dockerized with CI/CD support.

---

## 📦 Instalación Rápida

### Entorno Local
1.  **Dependencias:** `composer install && npm ci && npm run build`
2.  **Configuración:** `cp .env.example .env && php artisan key:generate`
3.  **Base de Datos:** `php artisan migrate --seed`
4.  **Ejecución:** `php artisan serve`

### Despliegue en Producción
El sistema incluye un `Dockerfile` optimizado y scripts para Railway/Render.
```bash
# Ejemplo de Build
docker build -t qa365-production .
```

---

## 🔑 Credenciales Demo (Seeder)
| Rol | Email | Password |
| :--- | :--- | :--- |
| **Admin** | `admin@qa.com` | `password` |
| **QA Manager** | `qa@qa.com` | `password` |
| **Supervisor** | `supervisor@qa.com` | `password` |
| **Agente** | `agent@qa.com` | `password` |

---

## 🛡️ Seguridad e Integridad
- **RBAC:** Roles y permisos granulares.
- **Privacy:** Cifrado AES-256 para llaves de API y datos sensibles.
- **Observabilidad:** Logs detallados de cada interacción con la IA para auditorías técnicas.

---

## 📝 Créditos
Propiedad de **Bearlytics - Lalo's Analysts**.
Desarrollado en colaboración con **Impulsa365**.

*Building the future of Quality Assurance.*
