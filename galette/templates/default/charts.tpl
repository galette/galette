        <div id="{php}echo \Galette\IO\Charts::MEMBERS_STATUS_PIE;{/php}">
        </div>
        <div id="{php}echo \Galette\IO\Charts::MEMBERS_STATEDUE_PIE;{/php}">
        </div>
        <div id="{php}echo \Galette\IO\Charts::CONTRIBS_TYPES_PIE;{/php}">
        </div>
        <div id="{php}echo \Galette\IO\Charts::COMPANIES_OR_NOT;{/php}">
        </div>
        <div id="{php}echo \Galette\IO\Charts::CONTRIBS_ALLTIME;{/php}">
        </div>
        <script type="text/javascript">
            $(function(){
                var _members_status_pie = jQuery.jqplot ('{php}echo \Galette\IO\Charts::MEMBERS_STATUS_PIE;{/php}', [{$charts.MembersStatusPie}],
                    {
                        title: '{_T string="Members by status" escape="js"}',
                        seriesDefaults: {
                            renderer: jQuery.jqplot.PieRenderer,
                            rendererOptions: {
                                showDataLabels: true
                            }
                        },
                        legend: { show:true }
                    }
                );

                var _members_statedue_pie = jQuery.jqplot ('{php}echo \Galette\IO\Charts::MEMBERS_STATEDUE_PIE;{/php}', [{$charts.MembersStateDuePie}],
                    {
                        title: '{_T string="Members by state of dues" escape="js"}',
                        seriesDefaults: {
                            renderer: jQuery.jqplot.PieRenderer,
                            rendererOptions: {
                                showDataLabels: true
                            }
                        },
                        legend: { show:true }
                    }
                );

                var _contribs_types_pie = jQuery.jqplot ('{php}echo \Galette\IO\Charts::CONTRIBS_TYPES_PIE;{/php}', [{$charts.ContribsTypesPie}],
                    {
                        title: '{_T string="Contributions types repartition" escape="js"}',
                        seriesDefaults: {
                            renderer: jQuery.jqplot.PieRenderer,
                            rendererOptions: {
                                showDataLabels: true
                            }
                        },
                        legend: { show:true }
                    }
                );

                var _companies_individuals = jQuery.jqplot ('{php}echo \Galette\IO\Charts::COMPANIES_OR_NOT;{/php}', [{$charts.CompaniesOrNot}],
                    {
                        title: '{_T string="Individuals and Companies" escape="js"}',
                        seriesDefaults: {
                            renderer: jQuery.jqplot.PieRenderer,
                            rendererOptions: {
                                showDataLabels: true
                            }
                        },
                        legend: { show:true }
                    }
                );

                var _contribs_alltime = jQuery.jqplot ('{php}echo \Galette\IO\Charts::CONTRIBS_ALLTIME;{/php}', [{$charts.ContribsAllTime}],
                    {
                        title: '{_T string="Contributions over time" escape="js"}',
                        seriesDefaults: {
                            renderer:$.jqplot.BarRenderer,
                            renderOptions:{
                                barDirection: 'vertical',
                                highlightMouseDown: true
                            },
                            pointLabels: {
                                show: true,
                                formatString: "%#.2f"
                            },
                        },
                        axes:{
                            xaxis:{
                                renderer: $.jqplot.CategoryAxisRenderer
                            }
                        }
                    }
                );
            });
        </script>
