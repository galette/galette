<div id="legende{$cur_ref}" class="texts_legend ui modal" title="{_T string="Existing variables"}">
    <div class="header">{_T string="Existing variables"}</div>
    <div class="content">
        <table class="ui very basic table">
    {foreach from=$legends item=legend}
        <tr>
            <th colspan="4">
                {$legend.title}
            </th>
        </tr>
        {foreach from=$legend.patterns item=pattern name=patternloop}
            {if $smarty.foreach.patternloop.index % 2 == 0}
                <tr>
            {/if}
            <th><tt>{$pattern.pattern|trim:'/'}</tt></th>
            <td class="back">
                {if isset($pattern.title)}{$pattern.title}{/if}
            </td>
            {if $smarty.foreach.patternloop.index % 2 != 0}
                </tr>
            {/if}
        {/foreach}
    {/foreach}
        </table>
    </div>
    <div class="actions"><div class="ui labeled icon deny button"><i class="times icon"></i> {_T string="Close"}</div></div>
</div>
<script type="text/javascript">

    var _addLegenButton = function(selector) {
        $(selector).append('<a id="btnlegend" class="ui tooltip" data-html="{_T string="Show existing variables" escape="js"}"><i class="circular inverted primary link icon info"></i> <span class="sr-only">{_T string="Show existing variables" escape="js"}</span></a>');
    };

    var _handleLegend = function(selector) {
        if (typeof selector == 'undefined') {
            selector = '{$cur_ref}';
        }
        $('#legende' + selector + ' h1').remove();
        $('#legende' + selector).dialog({
            autoOpen: false,
            modal: true,
            hide: 'fold',
            width: '60em',
            create: function (event, ui) {
                if ($(window ).width() < 767) {
                    $(this).dialog('option', {
                        'width': '95%',
                        'draggable': false
                    });
                }
            }
        }).dialog('close');

        $('#btnlegend').unbind('click').click(function(){
            $('#legende' + selector).dialog('open');
            return false;
        });
    };
</script>
