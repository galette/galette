    {*<p id="collapse" class="ui-state-default ui-corner-all">
        <span class="ui-icon ui-icon-circle-arrow-s"></span>
        {_T string="Collapse all"}
    </p>*}
    {* TODO: Dynamically generate required tabs entries *}
    {*<ul id="tabs">
        <li{if $current eq 'membership'} class="current_tab"{/if}><a href="?table=membership">{_T string="Membership"}</a></li>
        <li{if $current eq 'members'} class="current_tab"{/if}><a href="?table=members">{_T string="Members"}</a></li>
    </ul>*}
    <form action="config_fields.php" method="post" id="config_fields_form">
    <div {*class="tabbed" *}id="{$current}_tab">
        {*<a href="#" title="{_T string="Add a new category"}" id="add_category">{_T string="Add new category"}</a>*}
{foreach item=category from=$categories name=categories_list}
        <fieldset class="cssform large" id="cat_{$smarty.foreach.categories_list.iteration}">
    {assign var='catname' value=$category->category}
            <input type="hidden" name="categories[]" id="category{$smarty.foreach.categories_list.iteration}" value="{$category->id_field_category}"/>
            <legend class="ui-state-active ui-corner-top">{_T string="$catname"}</legend>
            <ul id="sortable_{$smarty.foreach.categories_list.iteration}" class="fields_list connectedSortable">
                <li class="listing ">
                    <span class="label">{_T string="Field name"}</span>
                    <span class="yesno">{_T string="Required"}</span>
                    <span class="yesnoadmin">{_T string="Visible"}</span>
                </li>
    {assign var='fs' value=$category->id_field_category}
    {foreach key=col item=field from=$categorized_fields[$fs] name=fields_list}
        {if $field.field_id neq 'id_adh'}
            {assign var='fid' value=$field.field_id}
                <li class="tbl_line_{if $smarty.foreach.fields_list.iteration % 2 eq 0}even{else}odd{/if}">
                    <span class="label">
                        <input type="hidden" name="fields[]" value="{$fid}"/>
                        <input type="hidden" name="{$fid}_category" value="{$category->id_field_category}"/>
                        <input type="hidden" name="{$fid}_label" value="{$field.label}"/>
                        {$field.label}
                    </span>
                    <span class="yesno" title="{if in_array($fid, $non_required)}{_T string="Field '%field' cannot be set as required." pattern="/%field/" replace=$field.label}{else}{_T string="Mark '%field' as (not) required" pattern="/%field/" replace=$field.label}{/if}">
                        <label for="{$fid}_required_yes">{_T string="Yes"}</label>
                        <input type="radio" name="{$fid}_required" id="{$fid}_required_yes" value="1"{if $field.required} checked="checked"{/if}{if in_array($fid, $non_required)} disabled="disabled"{/if}/>
                        <label for="{$fid}_required_no">{_T string="No"}</label>
                        <input type="radio" name="{$fid}_required" id="{$fid}_required_no" value="0"{if !$field.required} checked="checked"{/if}{if in_array($fid, $non_required)} disabled="disabled"{/if}/>
                    </span>
                    <span class="yesnoadmin" title="{_T string="Change '%field' visibility" pattern="/%field/" replace=$field.label}">
                        <label for="{$fid}_visible_yes">{_T string="Yes"}</label>
                        <input type="radio" name="{$fid}_visible" id="{$fid}_visible_yes" value="{php}echo Galette\Entity\FieldsConfig::VISIBLE;{/php}"{if $field.visible eq constant('Galette\Entity\FieldsConfig::VISIBLE')} checked="checked"{/if}/>
                        <label for="{$fid}_visible_no">{_T string="No"}</label>
                        <input type="radio" name="{$fid}_visible" id="{$fid}_visible_no" value="{php}echo Galette\Entity\FieldsConfig::HIDDEN;{/php}"{if $field.visible eq constant('Galette\Entity\FieldsConfig::HIDDEN')} checked="checked"{/if}/>
                        <label for="{$fid}_visible_admin">{_T string="Admin only"}</label>
                        <input type="radio" name="{$fid}_visible" id="{$fid}_visible_admin" value="{php}echo Galette\Entity\FieldsConfig::ADMIN;{/php}"{if $field.visible eq constant('Galette\Entity\FieldsConfig::ADMIN')} checked="checked"{/if}/>
                    </span>
                </li>
        {/if}
    {/foreach}
            </ul>
        </fieldset>
{/foreach}
    </div>
        <div class="button-container">
            <input type="submit" value="{_T string="Save"}"/>
        </div>
    </form>
    <script type="text/javascript">
{*        var _initSortable = function(){
            $('.fields_list').sortable({
                items: 'li:not(.listing)',
                connectWith: '.connectedSortable',
                update: function(event, ui) {
                    // When sort is updated, we must check for the newer category item belongs to
                    var _item = $(ui.item[0]);
                    var _category = _item.parent().prevAll('input[name^≃categories]').attr('value');
                    _item.find('input[name$=category]').attr('value', _category);
                }
            }).disableSelection();

            $('#members_tab').sortable({
                items: 'fieldset'
            });
        }

        var _bindCollapse = function() {
            $('#collapse').click(function(){
                var _this = $(this);
                var _expandTxt = '{_T string="Expand all"}';
                var _collapseTxt = '{_T string="Collapse all"}';

                var _span = _this.children('span');
                var _isExpand = false;

                var _child = _this.children('.ui-icon');

                if( _child.is('.ui-icon-circle-arrow-e') ) {
                    _this.html(_collapseTxt);
                } else {
                    _isExpand = true;
                    _this.html(_expandTxt);
                }
                _this.prepend(_span);

                _child.toggleClass('ui-icon-circle-arrow-e').toggleClass('ui-icon-circle-arrow-s');

                $('legend a').each(function(){
                    var _visible = $(this).parent('legend').parent('fieldset').children('ul').is(':visible');
                    if( _isExpand && _visible ) {
                        $(this).click();
                    } else if( !_isExpand && !_visible){
                        $(this).click();
                    }
                });
            });
        }
*}

        var _warnings = [];
        var _checkCoherence = function(index, elt){
            var _elt = $(elt);
            var _disabled = _elt.find('.yesno input:disabled, .yesnoadmin input:disabled');
            if ( _disabled.length == 0 ) {
                var _required = parseInt(_elt.find('.yesno input:checked').val());
                var _visible = parseInt(_elt.find('.yesnoadmin input:checked').val());

                if ( _required === 1 && _visible !== 1 ) {
                    _elt.find('.label').addClass('warnings');
                    _warnings[_warnings.length] = _elt;
                }
            }
        }

        var _bindForm = function(){
            $('#config_fields_form').submit(function(){

                _warnings = [];
                $('.warnings').removeClass('warnings');
                $('.fields_list li').each(_checkCoherence);

                if ( _warnings.length > 0 ) {
                    var _w = $('#warnings');

                    _w.find('li').remove();
                    $.each(_warnings, function(i,w){
                        var _val = w.find('.label').text().trim();
                        _w.find('ul').append('<li>' + _val + '</li>');
                        console.log(w);
                    });

                    _w.dialog({
                        modal: true,
                        buttons: {
                            Ok: function() {
                                $(this).dialog('close');
                            }
                        }
                    });
                    return false;
                } else {
                    return true;
                }
            });
        }

        $(function() {
            $('body').append($('<div id="warnings" title="{_T string="Warning" escape="js"}"><p>{_T string="Some warnings has been thrown:" escape="js"}</p><ul></ul><p>{_T string="Please correct above warnings to continue."}</p></div>').hide());

            _collapsibleFieldsets();
            _bindForm();
{*
            _bindCollapse();

            _initSortable();

            $('#add_category').click(function() {
                var _fieldsets = $('fieldset[id^=cat_]');
                var _cat_iter = _fieldsets.length + 1;

                var _fs = $(_fieldsets[0]).clone();
                _fs.attr('id', 'cat_' + _cat_iter).children('ul').attr('id', 'sortable_' + _cat_iter);
                _fs.find('li:not(.listing)').remove();

                var _legend = _fs.children('legend');
                var _a = _legend.children('a');

                _legend.html('<input type="text" name="categories[]" id="category' + _cat_iter + '" value="New category #' + _cat_iter + '"/>');
                _legend.prepend(_a);
                _a.spinDown();

                $('#{$current}_tab').append(_fs);
                _initSortable();
                _bindCollapse();

                $(this).attr('href', '#cat_' + _cat_iter);
                //Getting
                var _url = document.location.toString();
                if (_url.match('#')) { // the URL contains an anchor
                    var _url = _url.split('#')[0];
                }
                _url += '#cat_' + _cat_iter;

                document.location = _url;
                _legend.children(':input').focus();
                return false;
            });*}
        });
    </script>
