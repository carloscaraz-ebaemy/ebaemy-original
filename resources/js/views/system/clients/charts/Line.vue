<template>
    <div class="chart-line-container">
        <canvas ref="canvas"></canvas>
    </div>
</template>

<style>
    .chart-line-container {
        position: relative;
        height: 99%;
        width: 99%;
    }
</style>

<script>
    import Chart from 'chart.js';

    export default {
        props: ["data"],
        data() {
            return {
                chart: null,
                observer: null,
                _themeChangeHandler: null,
                isReady: false
            };
        },
        mounted() {
            // Esperar a que el DOM y el tema estén listos
            this.$nextTick(() => {
                setTimeout(() => {
                    this.setupThemeObserver();
                    this.createChart();
                    this.isReady = true;
                }, 100);
            });
        },
        beforeDestroy() {
            this.teardownThemeObserver();
            if (this.chart) {
                this.chart.destroy();
            }
        },
        watch: {
            data: {
                handler() {
                    if (this.isReady && this.data && this.data.datasets && this.data.datasets.length > 0) {
                        this.$nextTick(() => {
                            this.createChart();
                        });
                    }
                },
                deep: true
            }
        },
        methods: {
            getCSSVariable(name) {
                return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            },
            setupThemeObserver() {
                this.teardownThemeObserver();

                const handler = () => {
                    this.handleThemeChange();
                };

                // Escuchar evento personalizado themeChanged
                document.addEventListener('themeChanged', handler);
                window.addEventListener('theme:change', handler);

                this._themeChangeHandler = handler;
                this.observer = new MutationObserver(() => {
                    this.handleThemeChange();
                });

                this.observer.observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ['class', 'style'],
                });

                // También observar el style tag del tema
                const themeStyleTag = document.getElementById('theme-styles');
                if (themeStyleTag) {
                    this.observer.observe(themeStyleTag, {
                        childList: true,
                        subtree: true,
                        characterData: true
                    });
                }
            },
            teardownThemeObserver() {
                if (this.observer) {
                    this.observer.disconnect();
                    this.observer = null;
                }

                if (this._themeChangeHandler) {
                    document.removeEventListener('themeChanged', this._themeChangeHandler);
                    window.removeEventListener('theme:change', this._themeChangeHandler);
                    this._themeChangeHandler = null;
                }
            },
            handleThemeChange() {
                console.log('Cambio de tema detectado en Line.vue');
                if (!this.isReady) {
                    return;
                }
                setTimeout(() => {
                    if (this.data && this.data.datasets && this.data.datasets.length) {
                        this.createChart();
                    }
                }, 50);
            },
            hexToRgba(hex, alpha) {
                if (hex.length === 7) {
                    const r = parseInt(hex.slice(1, 3), 16);
                    const g = parseInt(hex.slice(3, 5), 16);
                    const b = parseInt(hex.slice(5, 7), 16);
                    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
                } else if (hex.length === 4) {
                    const r = parseInt(hex.slice(1, 2).repeat(2), 16);
                    const g = parseInt(hex.slice(2, 3).repeat(2), 16);
                    const b = parseInt(hex.slice(3, 4).repeat(2), 16);
                    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
                }
                return hex;
            },
            createChart() {
                if (!this.data || !this.data.datasets || this.data.datasets.length === 0) {
                    console.warn('No hay datos disponibles para el gráfico');
                    return;
                }

                // Validar que los datos no estén vacíos
                if (!this.data.labels || this.data.labels.length === 0) {
                    console.warn('No hay etiquetas disponibles para el gráfico');
                    return;
                }

                if (!this.data.datasets[0].data || this.data.datasets[0].data.length === 0) {
                    console.warn('No hay datos en el dataset');
                    return;
                }

                if (this.chart) {
                    this.chart.destroy();
                }

                const ctx = this.$refs.canvas.getContext('2d');
                
                // Obtener colores del tema actual
                const cssColors = {
                    primary: this.getCSSVariable('--primary-color') || '#2b5bfb',
                    highlight: this.getCSSVariable('--highlight-color') || '#7575e5',
                    dark: this.getCSSVariable('--dark-color') || '#273747'
                };
                
                console.log('Creando gráfico con colores:', cssColors);
                
                // Crear el degradado
                const gradient = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height);
                gradient.addColorStop(0, this.hexToRgba(cssColors.primary, 0.3));
                gradient.addColorStop(0.5, this.hexToRgba(cssColors.highlight, 0.2));
                gradient.addColorStop(1, this.hexToRgba(cssColors.primary, 0.05));

                const coloredDatasets = this.data.datasets.map((dataset) => {
                    return {
                        ...dataset,
                        borderColor: cssColors.primary,
                        backgroundColor: gradient,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: cssColors.primary,
                        pointBorderColor: cssColors.primary,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: cssColors.primary,
                        pointHoverBorderColor: cssColors.primary
                    };
                });

                this.chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: this.data.labels,
                        datasets: coloredDatasets,
                    },
                    options: {
                        legend: {
                            display: false
                        },
                        responsive: true,
                        maintainAspectRatio: false,
                        elements: {
                            line: {
                                borderJoinStyle: 'round',
                                borderWidth: 3
                            }
                        },
                        scales: {
                            yAxes: [{
                                gridLines: {
                                    display: false,
                                    drawBorder: false
                                },
                                ticks: {
                                    beginAtZero: true,
                                    fontColor: cssColors.dark,
                                    fontSize: 12
                                }
                            }],
                            xAxes: [{
                                gridLines: {
                                    display: false,
                                    drawBorder: false
                                },
                                ticks: {
                                    fontColor: cssColors.dark,
                                    fontSize: 12
                                }
                            }]
                        },
                        plugins: {
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                displayColors: true
                            }
                        }
                    },
                });
            }
        }
    };
</script>
