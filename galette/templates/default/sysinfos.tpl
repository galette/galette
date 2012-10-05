        <div class="tabbed">
            <div id="sysinfos">
                <ul>
                    <li><a href="#raw_sysinfos">{_T string="Raw data"}</a></li>
                    <li><a href="#fmtd_sysinfos">{_T string="HTML display"}</a></li>
                </ul>
                <section id="raw_sysinfos">
                    <h3>{_T string="Raw data"}</h3>
                    <div>
                        <pre>{$rawinfos}</pre>
                    </div>
                </section>
                <section id="fmtd_sysinfos">
                    <legend >{_T string="HTML display"}</legend>
                    <div>
                        <p>{_T string="HTML display is not yet available. Sorry."}</p>
                    </div>
                </section>
            </div>
        </div>
        <script type="text/javascript">
            $(function() {
                {* Tabs *}
                $('#sysinfos').tabs();
            });
        </script>

