<header id="top-navbar" class="ui large top fixed menu">
    <div class="ui fluid container">
        <a class="toc item">
            <i class="sidebar icon"></i>
        </a>
        <div class="header item">
            <img src="{path_for name="logo"}" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" class="logo" />
            <span>{$preferences->pref_nom}</span>
        </div>

        {include
            file="navigation/public_pages.tpl"
            tips_position="bottom center"
            sign_in=true
            sign_in_side='right'
        }

        {include
            file="ui_elements/languages.tpl"
            ui="dropdown"
        }

    </div>
</header>
