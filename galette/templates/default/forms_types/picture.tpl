<div class="field">
    <label>{_T string="Picture:"}</label>
    {if $member->id}
        {assign var="photo_id" value=$member->id}
    {else}
        {assign var="photo_id" value=0}
    {/if}
    <img id="photo_adh" src="{path_for name="photo" data=["id" => $photo_id, "rand" => $time]}" class="picture" width="{$member->picture->getOptimalWidth()}" height="{$member->picture->getOptimalHeight()}" alt="{_T string="Picture"}"/><br/>
{if $member->hasPicture() eq 1 }
    <label for="del_photo" class="labelalign">{_T string="Delete image"}</label> <input type="checkbox" name="del_photo" id="del_photo" value="1"/><br/>
{/if}
    <input class="labelalign" type="file" name="photo"/>
</div>
