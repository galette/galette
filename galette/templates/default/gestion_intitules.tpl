{extends file="page.tpl"}

{block name="content"}
<form action="{path_for name="editEntitled" data=["class" => $url_class, "action" => {_T string="add" domain="routes"}]}" method="post" class="tabbed">
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
