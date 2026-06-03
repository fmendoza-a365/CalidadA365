import './bootstrap';
import Alpine from 'alpinejs';

// Theme Management
const ThemeManager = {
    init() {
        // Check for saved theme or system preference
        const savedTheme = localStorage.getItem('theme');
        const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (savedTheme === 'dark' || (!savedTheme && systemDark)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    },

    toggle() {
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        return isDark;
    },

    get isDark() {
        return document.documentElement.classList.contains('dark');
    }
};

// Initialize theme before Alpine
ThemeManager.init();

// Make available globally
window.ThemeManager = ThemeManager;

// Alpine.js Data Components
Alpine.data('sidebar', () => ({
    open: window.innerWidth >= 1024,
    desktop: window.innerWidth >= 1024,
    collapsed: localStorage.getItem('sidebarCollapsed') === 'true',

    init() {
        window.addEventListener('resize', () => {
            this.desktop = window.innerWidth >= 1024;

            if (this.desktop) {
                this.open = true;
            } else {
                this.collapsed = false;
            }
        });
    },

    toggle() {
        if (this.desktop) {
            this.toggleCollapse();
            return;
        }

        this.open = !this.open;
    },

    toggleCollapse() {
        this.collapsed = !this.collapsed;
        localStorage.setItem('sidebarCollapsed', this.collapsed ? 'true' : 'false');
    }
}));

Alpine.data('themeToggle', () => ({
    isDark: ThemeManager.isDark,

    toggle() {
        this.isDark = ThemeManager.toggle();
    }
}));

Alpine.data('dropdown', () => ({
    open: false,

    toggle() {
        this.open = !this.open;
    },

    close() {
        this.open = false;
    }
}));

function passwordToggleIcon(visible) {
    return visible
        ? `<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c1.546 0 3.018-.332 4.343-.93M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0A3 3 0 1112 9a3 3 0 012.122 5.122z" /></svg>`
        : `<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.43 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>`;
}

function enhancePasswordInputs() {
    document.querySelectorAll('input[type="password"]:not([data-password-toggle-ready])').forEach((input) => {
        input.dataset.passwordToggleReady = 'true';
        input.classList.add('pr-12');

        let container = input.parentElement;

        if (!container.classList.contains('relative')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'relative';
            container.insertBefore(wrapper, input);
            wrapper.appendChild(input);
            container = wrapper;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'absolute inset-y-0 right-0 flex w-11 items-center justify-center text-gray-400 transition hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-gray-500 dark:hover:text-gray-200';
        button.setAttribute('aria-label', 'Mostrar contraseña');
        button.innerHTML = passwordToggleIcon(false);

        button.addEventListener('click', () => {
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
            button.innerHTML = passwordToggleIcon(visible);
            input.focus({ preventScroll: true });
        });

        container.appendChild(button);
    });
}

document.addEventListener('DOMContentLoaded', enhancePasswordInputs);
document.addEventListener('alpine:init', () => {
    queueMicrotask(enhancePasswordInputs);
});

Alpine.data('audioReview', (config = {}) => ({
    audioUrl: config.audioUrl || '',
    timeline: config.timeline || {},
    rawBars: config.timeline?.bars || [],
    rawSegments: config.timeline?.segments || [],
    rawSilences: config.timeline?.silences || [],
    visualBars: [],
    segments: [],
    silentSegments: [],
    timelineDuration: Number(config.timeline?.duration || 0),
    nativeDuration: 0,
    duration: Number(config.timeline?.duration || 0),
    currentTime: 0,
    playing: false,
    speed: '1',
    selectedTurnId: null,

    init() {
        this.normalizeTimeline();

        this.$nextTick(() => {
            if (!this.$refs.audio) {
                return;
            }

            this.$refs.audio.playbackRate = Number(this.speed);
        });
    },

    get progress() {
        const duration = this.safeDuration();

        if (!duration) {
            return 0;
        }

        return this.percent(this.currentTime, duration);
    },

    get currentLabel() {
        return this.formatTime(this.currentTime);
    },

    get durationLabel() {
        return this.formatTime(this.duration);
    },

    get activeSegment() {
        if (!this.segments.length) {
            return null;
        }

        const active = this.segments.find((segment) => this.currentTime >= segment.start && this.currentTime < segment.end);

        if (active) {
            return active;
        }

        const lastSegment = this.segments[this.segments.length - 1];

        return lastSegment && this.currentTime >= lastSegment.end ? lastSegment : null;
    },

    get activeTurnId() {
        return this.activeSegment?.turn_id || this.selectedTurnId || null;
    },

    get activeSegmentLabel() {
        if (!this.activeSegment) {
            return 'Sin segmento activo';
        }

        return `${this.activeSegment.label} · ${this.activeSegment.emotion_label} · tramo ${this.activeSegment.start_label}-${this.activeSegment.end_label}`;
    },

    get eventSegments() {
        if (!this.segments.length) {
            return [];
        }

        return this.segments
            .filter((segment, index, items) => {
                const previous = items[index - 1];
                const score = Math.abs(Number(segment.score || 0));
                const isNeutral = (segment.sentiment || 'neutro') === 'neutro'
                    && ['calma', 'neutro', 'neutral'].includes(segment.emotion || 'calma')
                    && score < 0.35;

                if (isNeutral) {
                    return false;
                }

                return index === 0
                    || index === items.length - 1
                    || score >= 0.35
                    || segment.sentiment !== previous?.sentiment
                    || segment.emotion !== previous?.emotion;
            })
            .slice(0, 48);
    },

    onLoadedMetadata() {
        const nativeDuration = Number(this.$refs.audio?.duration || 0);

        if (!Number.isFinite(nativeDuration) || nativeDuration <= 0) {
            return;
        }

        this.nativeDuration = nativeDuration;

        const serverDuration = Number(this.timelineDuration || 0);
        const tolerance = Math.max(2, serverDuration * 0.25);
        const nextDuration = serverDuration > 0 && Math.abs(nativeDuration - serverDuration) > tolerance
            ? serverDuration
            : nativeDuration || serverDuration || this.duration;
        if (Math.abs(nextDuration - this.duration) >= 0.1) {
            this.duration = nextDuration;
            this.normalizeTimeline();
            this.currentTime = this.clampTime(this.currentTime);
        }
    },

    onTimeUpdate() {
        this.currentTime = Number(this.$refs.audio?.currentTime || 0);
    },

    onEnded() {
        this.playing = false;
    },

    toggle() {
        if (!this.$refs.audio) {
            return;
        }

        if (this.$refs.audio.paused) {
            this.$refs.audio.play();
            this.playing = true;
            return;
        }

        this.$refs.audio.pause();
        this.playing = false;
    },

    seek(seconds) {
        const nextTime = this.clampTime(seconds);
        this.currentTime = nextTime;

        if (this.$refs.audio) {
            try {
                this.$refs.audio.currentTime = nextTime;
            } catch (error) {
                this.currentTime = nextTime;
            }
        }
    },

    selectTurn(seconds, turnId = null) {
        this.selectedTurnId = turnId;
        this.seek(seconds);

        if (turnId) {
            this.scrollToTurn(turnId);
        }
    },

    selectSegment(segment) {
        if (!segment) {
            return;
        }

        this.selectTurn(segment.start, segment.turn_id || null);
    },

    scrollToTurn(turnId) {
        this.$nextTick(() => {
            const target = document.getElementById(turnId);

            if (!target) {
                return;
            }

            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    },

    seekFromTimeline(event) {
        if (!this.safeDuration()) {
            return;
        }

        const track = event.currentTarget;
        const rect = track.getBoundingClientRect();
        const ratio = Math.min(Math.max((event.clientX - rect.left) / Math.max(rect.width, 1), 0), 1);

        this.seek(this.safeDuration() * ratio);
    },

    seekFromWaveform(event) {
        this.seekFromTimeline(event);
    },

    seekFromTrack(event) {
        this.seekFromTimeline(event);
    },

    normalizeTimeline() {
        const duration = this.safeDuration();

        this.segments = this.rawSegments.map((segment) => {
            const rawStart = Math.max(0, Number(segment.start || 0));
            const rawEnd = Math.max(rawStart + 0.1, Number(segment.end || rawStart + 1));
            const start = Math.min(rawStart, Math.max(duration - 0.1, 0));
            const end = Math.min(duration, Math.max(start + 0.1, rawEnd));

            return {
                ...segment,
                start,
                end,
                left: this.percent(start, duration),
                width: Math.max(this.percent(end - start, duration), 0.35),
            };
        });

        this.silentSegments = this.rawSilences.map((segment) => {
            const rawStart = Math.max(0, Number(segment.start || 0));
            const rawEnd = Math.max(rawStart + 0.1, Number(segment.end || rawStart + 1));
            const start = Math.min(rawStart, Math.max(duration - 0.1, 0));
            const end = Math.min(duration, Math.max(start + 0.1, rawEnd));

            return {
                ...segment,
                start,
                end,
                left: this.percent(start, duration),
                width: Math.max(this.percent(end - start, duration), 0.35),
            };
        });

        this.visualBars = this.buildVisualBars(duration);
    },

    buildVisualBars(duration) {
        const count = this.rawBars.length || 120;

        return Array.from({ length: count }, (_, index) => {
            const original = this.rawBars[index] || {};
            const time = duration > 0 ? (duration / count) * (index + 0.5) : Number(original.time || 0);
            const segment = this.segmentAtSecond(time);

            return {
                index,
                time,
                height: Number(original.height || 30),
                color: segment?.color || '#64748b',
                speaker: segment?.speaker || original.speaker || 'system',
                sentiment: segment?.sentiment || original.sentiment || 'neutro',
                emotion: segment?.emotion || original.emotion || 'calma',
            };
        });
    },

    segmentAtSecond(second) {
        return this.segments.find((segment) => second >= segment.start && second < segment.end) || null;
    },

    markerLeft(segment) {
        return Math.min(99, Math.max(1, Number(segment?.left || 0)));
    },

    safeDuration() {
        return Math.max(Number(this.duration || this.timelineDuration || 0), 1);
    },

    clampTime(seconds) {
        return Math.min(Math.max(Number(seconds) || 0, 0), this.safeDuration());
    },

    percent(value, duration) {
        const safeDuration = Math.max(Number(duration || 0), 1);

        return Math.min(100, Math.max(0, (Number(value || 0) / safeDuration) * 100));
    },

    setSpeed(value) {
        const speed = Number(value) || 1;

        if (this.$refs.audio) {
            this.$refs.audio.playbackRate = speed;
        }
    },

    speakerName(speaker) {
        if (speaker === 'client') {
            return 'Cliente';
        }

        if (speaker === 'agent') {
            return 'Agente';
        }

        return 'Sistema';
    },

    segmentPreview(segment) {
        const message = String(segment?.message || '').replace(/\s+/g, ' ').trim();

        if (!message) {
            return 'Sin extracto disponible';
        }

        return message.length > 150 ? `${message.slice(0, 147)}...` : message;
    },

    eventTitle(segment) {
        return `${segment.start_label} · ${this.speakerName(segment.speaker)} · ${segment.emotion_label}`;
    },

    formatTime(seconds) {
        const safeSeconds = Math.max(0, Math.floor(Number(seconds) || 0));
        const hours = Math.floor(safeSeconds / 3600);
        const minutes = Math.floor((safeSeconds % 3600) / 60);
        const remainingSeconds = safeSeconds % 60;

        if (hours > 0) {
            return `${hours}:${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
        }

        return `${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
    }
}));

window.Alpine = Alpine;
Alpine.start();
