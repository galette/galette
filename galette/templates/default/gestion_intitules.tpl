{extends file="page.tpl"}

{block name="content"}
<form action="{path_for name="doAddEntitled" data=["class" => $url_class]}" method="post" class="tabbed">
<div id="intitules_tabs">
    {include file="gestion_intitule_content.tpl"}
</div>
</form>
{/block}

{block name="javascripts"}
    <script type="text/javascript">
        $(function() {
            {include file="js_removal.tpl"}
        });
    </script>
{/block}
