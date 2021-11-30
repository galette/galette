<div class="ui horizontal card">
    <div class="image">
        {if $login->isAdmin() or $login->isStaff() or $login->login eq $member->login}
            <a class="ui right corner inverted label">
                <i class="upload icon"></i>
            </a>
        {/if}
        <img
                src="{path_for name="photo" data=["id" => $member->id, "rand" => $time]}"
                width="{$member->picture->getOptimalWidth()}"
                height="{$member->picture->getOptimalHeight()}"
                alt="{_T string="Picture"}"
                {if $login->isAdmin() or $login->isStaff() or $login->login eq $member->login} title="{_T string="You can drop new image here to get photo changed"}" class="tooltip"{/if}
                id="photo_adh"/>
    </div>
    <div class="content">
        <div class="header right aligned">
            {$member->sname}
        </div>

        <div class="meta right aligned">
            <span>{$member->sstatus}</span>
            <span class="ui {$member->getRowClass()} horizontal icon label tooltip" title="{$member->getDues()|escape}">
                    <i class="icon cookie"></i>
                </span>
        </div>
        <div class="description">
            <div class="ui relaxed divided list">
                {if $member->phone || $member->gsm}
                    <div class="item">
                        <div class="content">
                            <span class="header">{_T string="Phone"}</span>
                            <div class="description">
                                {if $member->phone}
                                    {$member->phone}
                                {/if}
                                {if $member->gsm}
                                    {if $member->phone}, {/if}
                                    {$member->gsm}
                                {/if}
                            </div>
                        </div>
                    </div>
                {/if}
                {if $member->getEmail()}
                    <div class="item">
                        <div class="content">
                            <span class="blue header">{_T string="Email"}</span>
                            <div class="description">{$member->getEMail()}</div>
                        </div>
                    </div>
                {/if}
            </div>
        </div>
    </div>
</div>
