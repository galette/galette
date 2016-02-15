{extends file="page.tpl"}
{block name="content"}
        <div id="{\Galette\IO\Charts::MEMBERS_STATUS_PIE}">
        </div>
        <div id="{\Galette\IO\Charts::MEMBERS_STATEDUE_PIE}">
        </div>
        <div id="{\Galette\IO\Charts::CONTRIBS_TYPES_PIE}">
        </div>
        <div id="{\Galette\IO\Charts::COMPANIES_OR_NOT}">
        </div>
        <div id="{\Galette\IO\Charts::CONTRIBS_ALLTIME}">
        </div>
{/block}

{block name="javascripts"}
        <script type="text/javascript">
            $(function(){
                var _members_status_pie = jQuery.jqplot ('{\Galette\IO\Charts::MEMBERS_STATUS_PIE}', [{$charts.MembersStatusPie}],
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

                var _members_statedue_pie = jQuery.jqplot ('{\Galette\IO\Charts::MEMBERS_STATEDUE_PIE}', [{$charts.MembersStateDuePie}],
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

                var _contribs_types_pie = jQuery.jqplot ('{\Galette\IO\Charts::CONTRIBS_TYPES_PIE}', [{$charts.ContribsTypesPie}],
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

                var _companies_individuals = jQuery.jqplot ('{\Galette\IO\Charts::COMPANIES_OR_NOT}', [{$charts.CompaniesOrNot}],
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

                var _contribs_alltime = jQuery.jqplot ('{\Galette\IO\Charts::CONTRIBS_ALLTIME}', [{$charts.ContribsAllTime}],
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

{/block}
