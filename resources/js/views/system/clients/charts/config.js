export const lineChartOptions = {
    legend: {
        display: false
    },
    responsive: true,
    maintainAspectRatio: false,
    elements: {
        line: {
            tension: 0.4,
            borderWidth: 3
        }
    },
    scales: {
        yAxes: [
            {
                gridLines: {
                    display: false, // Eliminamos las líneas cuadriculares
                    drawBorder: false
                },
                ticks: {
                    beginAtZero: true,
                    fontColor: '#273747',
                    fontSize: 12
                }
            }
        ],
        xAxes: [
            {
                gridLines: {
                    display: false, // Eliminamos las líneas cuadriculares
                    drawBorder: false
                },
                ticks: {
                    fontColor: '#273747',
                    fontSize: 12
                }
            }
        ]
    }
};
