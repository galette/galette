{if $ajax}
    {assign var="extend" value='ajax.tpl'}
{else}
    {assign var="extend" value='page.tpl'}
{/if}
{extends file=$extend}
{block name="content"}
    <form action="{path_for name="attendance_sheet"}" id="sheet_details_form" method="POST" class="ui form">
        <div class="ui top attached header">
            {_T string="Some details about your attendance sheet..."} - <span>{_T string="%s attendees" pattern="/%s/" replace=$selection|@count}</span>
        </div>
        <div class="ui bottom attached segment">
            <div class="active content field">
                <div class="inline field">
                    <label for="sheet_type">{_T string="Sheet type"}</label>
                    <input type="text" name="sheet_type" id="sheet_type" value="{_T string="Attendance sheet"}" required/>
                </div>
                <div class="inline field">
                    <label for="sheet_title">{_T string="Title"}</label>
                    <input type="text" name="sheet_title" id="sheet_title"/>
                </div>
                <div class="inline field">
                    <label for="sheet_sub_title">{_T string="Subtitle"}</label>
                    <input type="text" name="sheet_sub_title" id="sheet_sub_title"/>
                </div>
                <div class="inline field">
                    <label for="sheet_date">{_T string="Date"}</label>
                    <input type="text" name="sheet_date" id="sheet_date"/>
                    <span class="exemple">{_T string="(yyyy-mm-dd format)"}</span>
                </div>
                <div class="inline field">
                    <label for="sheet_photos">{_T string="With photos?"}</label>
                    <input type="checkbox" name="sheet_photos" id="sheet_photos" value="1"/>
{foreach $selection as $member}
                    <input type="hidden" name="selection[]" value="{$member}"/>
{/foreach}
                {include file="forms_types/csrf.tpl"}
                </div>
            </div>
        </div>
{if not $ajax}
        <div class="ui basic center aligned segment">
            <button type="submit" class="ui labeled icon primary button">
                <i class="file pdf icon" aria-hidden="true"></i>
                {_T string="Generate"}
            </button>
        </div>
{/if}

    </form>
{/block}
