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

Alpine.data('audioReview', (config = {}) => ({
    audioUrl: config.audioUrl || '',
    timeline: config.timeline || {},
    rawBars: config.timeline?.bars || [],
    rawSegments: config.timeline?.segments || [],
    visualBars: [],
    segments: [],
    timelineDuration: Number(config.timeline?.duration || 0),
    nativeDuration: 0,
    duration: Number(config.timeline?.duration || 0),
    currentTime: 0,
    playing: false,
    speed: '1',
    selectedTurnId: null,

    init() {
        this.normalizeTimeline();
        this.loadAudioWaveform();

        this.$nextTick(() => {
            if (!this.$refs.audio) {
                return;
            }

            this.$refs.audio.playbackRate = Number(this.speed);
        });
    },

    get progress() {
        if (!this.duration) {
            return 0;
        }

        return Math.min(100, Math.max(0, (this.currentTime / this.duration) * 100));
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

        return this.segments.find((segment) => this.currentTime >= segment.start && this.currentTime < segment.end) || null;
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

        const nextDuration = Math.max(this.timelineDuration || 0, nativeDuration);
        if (Math.abs(nextDuration - this.duration) >= 0.5) {
            this.duration = nextDuration;
            this.normalizeTimeline();
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
        const nextTime = Math.min(Math.max(Number(seconds) || 0, 0), this.duration || Number(seconds) || 0);
        this.currentTime = nextTime;

        if (this.$refs.audio) {
            this.$refs.audio.currentTime = nextTime;
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

    seekFromWaveform(event) {
        if (!this.duration) {
            return;
        }

        const rect = event.currentTarget.getBoundingClientRect();
        const style = window.getComputedStyle(event.currentTarget);
        const paddingLeft = Number.parseFloat(style.paddingLeft) || 0;
        const paddingRight = Number.parseFloat(style.paddingRight) || 0;
        const innerWidth = Math.max(rect.width - paddingLeft - paddingRight, 1);
        const x = Math.min(Math.max(event.clientX - rect.left - paddingLeft, 0), innerWidth);
        const ratio = x / innerWidth;

        this.seek(this.duration * ratio);
    },

    seekFromTrack(event) {
        if (!this.duration) {
            return;
        }

        const rect = event.currentTarget.getBoundingClientRect();
        const ratio = Math.min(Math.max((event.clientX - rect.left) / Math.max(rect.width, 1), 0), 1);

        this.seek(this.duration * ratio);
    },

    normalizeTimeline() {
        const duration = this.safeDuration();

        this.segments = this.rawSegments.map((segment) => {
            const start = Math.max(0, Number(segment.start || 0));
            const end = Math.max(start + 1, Number(segment.end || start + 1));

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

    async loadAudioWaveform() {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;

        if (!this.audioUrl || !AudioContextClass || typeof fetch !== 'function') {
            return;
        }

        try {
            const response = await fetch(this.audioUrl, { credentials: 'same-origin' });

            if (!response.ok) {
                return;
            }

            const audioData = await response.arrayBuffer();
            const audioContext = new AudioContextClass();
            const audioBuffer = await audioContext.decodeAudioData(audioData.slice(0));
            const waveformBars = this.waveformBarsFromBuffer(audioBuffer, this.rawBars.length || 120);

            if (waveformBars.length) {
                this.rawBars = waveformBars;
                this.visualBars = this.buildVisualBars(this.safeDuration());
            }

            if (typeof audioContext.close === 'function') {
                await audioContext.close();
            }
        } catch (error) {
            // The server-generated timeline stays visible if the browser cannot decode the file.
        }
    },

    waveformBarsFromBuffer(audioBuffer, count) {
        const channel = audioBuffer.getChannelData(0);

        if (!channel.length || !count) {
            return [];
        }

        const samplesPerBar = Math.max(Math.floor(channel.length / count), 1);
        const levels = [];

        for (let index = 0; index < count; index += 1) {
            const start = index * samplesPerBar;
            const end = Math.min(start + samplesPerBar, channel.length);
            const step = Math.max(Math.floor((end - start) / 240), 1);
            let sum = 0;
            let reads = 0;

            for (let sample = start; sample < end; sample += step) {
                const value = channel[sample] || 0;
                sum += value * value;
                reads += 1;
            }

            levels.push(reads ? Math.sqrt(sum / reads) : 0);
        }

        const maxLevel = Math.max(...levels, 0.001);
        const duration = this.safeDuration();

        return levels.map((level, index) => ({
            index,
            time: duration > 0 ? (duration / count) * (index + 0.5) : 0,
            height: 16 + Math.min(78, (level / maxLevel) * 78),
        }));
    },

    segmentAtSecond(second) {
        return this.segments.find((segment) => second >= segment.start && second < segment.end) || null;
    },

    safeDuration() {
        return Math.max(Number(this.duration || 0), Number(this.timelineDuration || 0), 1);
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
