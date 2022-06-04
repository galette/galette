{extends file="page.tpl"}
{block name="content"}
        <div class="ui centered grid">
                <div class="eight wide mobile five wide computer column">
                        <h2 class="uid header">{_T string="Members by status" escape="js"}</h2>
                        <canvas id="{\Galette\IO\Charts::MEMBERS_STATUS_PIE}" width="640" height="640"></canvas>
                </div>
                <div class="eight wide mobile five wide computer column">
                        <h2 class="uid header">{_T string="Members by state of dues" escape="js"}</h2>
                        <canvas id="{\Galette\IO\Charts::MEMBERS_STATEDUE_PIE}" width="640" height="640"></canvas>
                </div>
                <div class="eight wide mobile five wide computer column">
                        <h2 class="uid header">{_T string="Contributions types repartition" escape="js"}</h2>
                        <canvas id="{\Galette\IO\Charts::CONTRIBS_TYPES_PIE}" width="640" height="640"></canvas>
                </div>
                <div class="eight wide mobile four wide computer column">
                        <h2 class="ui center aligned header">{_T string="Individuals and Companies" escape="js"}</h2>
                        <canvas id="{\Galette\IO\Charts::COMPANIES_OR_NOT}" width="320" height="320"></canvas>
                </div>
                <div class="sixteen wide mobile twelve wide computer column">
                        <h2 class="ui center aligned header">{_T string="Contributions over time" escape="js"}</h2>
                        <canvas id="{\Galette\IO\Charts::CONTRIBS_ALLTIME}" width="960" height="320"></canvas>
                </div>
        </div>
{/block}

{block name="javascripts"}
        <script type="module">
            // Chart.js plugins
            Chart.register(ChartDataLabels);
            const autocolors = window['chartjs-plugin-autocolors'];

            // Default options
            const lighten = (color, value) => Chart.helpers.color(color).lighten(value).rgbString();
            Chart.defaults.font.family = "'PT Sans','Helvetica Neue',Arial,Helvetica,sans-serif";
            Chart.defaults.font.color = 'rgb(99, 107, 112)';
            Chart.defaults.backgroundColor = 'rgb(158, 226, 226)';

            const _members_status_pie_ctx = document.getElementById('{\Galette\IO\Charts::MEMBERS_STATUS_PIE}').getContext('2d');
            let _members_status_pie_total = {$charts.MembersStatusPieData}.reduce(getSum, 0);
            const _members_status_pie = new Chart(_members_status_pie_ctx, {
                type: 'pie',
                data: {
                    labels: {$charts.MembersStatusPieLabels},
                    datasets: [{
                        label: '{_T string="Members by status" escape="js"}',
                        data: {$charts.MembersStatusPieData},
                    }]
                },
                plugins: [
                    autocolors
                ],
                options: {
                    plugins: {
                        autocolors: {
                            mode: 'data',
                            customize(context) {
                              const colors = context.colors;
                              return {
                                background: lighten(colors.background, 0.2),
                                border: lighten(colors.border, 0.2)
                              };
                            }
                        },
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 10,
                            }
                        },
                        tooltip: {
                            position: 'nearest',
                            backgroundColor: 'rgb(99, 107, 112)',
                            caretPadding: 15,
                            boxPadding: 8,
                        },
                        datalabels: {
                            formatter: function(value, context) {
                                  return Math.fround(value / _members_status_pie_total * 100).toFixed(1) + ' %';
                            },
                            backgroundColor: 'rgb(255, 255, 255)',
                            borderColor: 'rgb(229, 229, 229)',
                            borderWidth: 1,
                            borderRadius: 4,
                        }
                    }
                }
            });

            const _members_statedue_pie_ctx = document.getElementById('{\Galette\IO\Charts::MEMBERS_STATEDUE_PIE}').getContext('2d');
            let _members_statedue_pie_total = {$charts.MembersStateDuePieData}.reduce(getSum, 0);
            const _members_statedue_pie = new Chart(_members_statedue_pie_ctx, {
                type: 'pie',
                data: {
                    labels: {$charts.MembersStateDuePieLabels},
                    datasets: [{
                        label: '{_T string="Members by state of dues" escape="js"}',
                        data: {$charts.MembersStateDuePieData}
                    }]
                },
                plugins: [
                    autocolors
                ],
                options: {
                    plugins: {
                        autocolors: {
                            mode: 'data',
                            customize(context) {
                              const colors = context.colors;
                              return {
                                background: lighten(colors.background, 0.2),
                                border: lighten(colors.border, 0.2)
                              };
                            }
                        },
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 10,
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgb(99, 107, 112)',
                            caretPadding: 15,
                            boxPadding: 8,
                        },
                        datalabels: {
                            formatter: function(value, context) {
                                  return Math.fround(value / _members_statedue_pie_total * 100).toFixed(1) + ' %';
                            },
                            backgroundColor: 'rgb(255, 255, 255)',
                            borderColor: 'rgb(229, 229, 229)',
                            borderWidth: 1,
                            borderRadius: 4,
                        }
                    }
                }
            });

            const _contribs_types_pie_ctx = document.getElementById('{\Galette\IO\Charts::CONTRIBS_TYPES_PIE}').getContext('2d');
            let _contribs_types_pie_total = {$charts.ContribsTypesPieData}.reduce(getSum, 0);
            const _contribs_types_pie_pie = new Chart(_contribs_types_pie_ctx, {
                type: 'pie',
                data: {
                    labels: {$charts.ContribsTypesPieLabels},
                    datasets: [{
                        label: '{_T string="Contributions types repartition" escape="js"}',
                        data: {$charts.ContribsTypesPieData}
                    }]
                },
                plugins: [
                    autocolors
                ],
                options: {
                    plugins: {
                        autocolors: {
                            mode: 'data',
                            customize(context) {
                              const colors = context.colors;
                              return {
                                background: lighten(colors.background, 0.2),
                                border: lighten(colors.border, 0.2)
                              };
                            }
                        },
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 10,
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgb(99, 107, 112)',
                            caretPadding: 15,
                            boxPadding: 8,
                        },
                        datalabels: {
                            formatter: function(value, context) {
                                  return Math.fround(value / _contribs_types_pie_total * 100).toFixed(1) + ' %';
                            },
                            backgroundColor: 'rgb(255, 255, 255)',
                            borderColor: 'rgb(229, 229, 229)',
                            borderWidth: 1,
                            borderRadius: 4,
                        }
                    }
                }
            });

            const _companies_individuals_ctx = document.getElementById('{\Galette\IO\Charts::COMPANIES_OR_NOT}').getContext('2d');
            let _companies_individuals_total = {$charts.CompaniesOrNotData}.reduce(getSum, 0);
            const _companies_individuals = new Chart(_companies_individuals_ctx, {
                type: 'doughnut',
                data: {
                    labels: {$charts.CompaniesOrNotLabels},
                    datasets: [{
                        label: '{_T string="Individuals and Companies" escape="js"}',
                        data: {$charts.CompaniesOrNotData}
                    }]
                },
                plugins: [
                    autocolors
                ],
                options: {
                    plugins: {
                        autocolors: {
                            mode: 'data',
                            customize(context) {
                              const colors = context.colors;
                              return {
                                background: lighten(colors.background, 0.2),
                                border: lighten(colors.border, 0.2)
                              };
                            }
                        },
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 10,
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgb(99, 107, 112)',
                            caretPadding: 15,
                            boxPadding: 8,
                        },
                        datalabels: {
                            formatter: function(value, context) {
                                  return Math.fround(value / _companies_individuals_total * 100).toFixed(1) + ' %';
                            },
                            backgroundColor: 'rgb(255, 255, 255)',
                            borderColor: 'rgb(229, 229, 229)',
                            borderWidth: 1,
                            borderRadius: 4,
                        }
                    }
                }
            });

            const _contribs_alltime_ctx = document.getElementById('{\Galette\IO\Charts::CONTRIBS_ALLTIME}').getContext('2d');
            const _contribs_alltime = new Chart(_contribs_alltime_ctx, {
                type: 'bar',
                data: {
                    labels: {$charts.ContribsAllTimeLabels},
                    datasets: [{
                        label: '{_T string="Total contributions for the month" escape="js"}',
                        data: {$charts.ContribsAllTimeData}
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgb(99, 107, 112)',
                            caretPadding: 10,
                            boxPadding: 8,
                        },
                        datalabels: {
                            backgroundColor: 'rgb(255, 255, 255)',
                            borderColor: 'rgb(229, 229, 229)',
                            borderWidth: 1,
                            borderRadius: 4,
                        }
                    }
                }
            });

            function getSum(total, num) {
                return total + Math.round(num);
            }
        </script>
{/block}
