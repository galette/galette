{if $login->isLogged()}
        <h1 class="nojs">Test 1</h1>
        <ul>
            <li><a href="{$galette_base_path}{$galette_galette_test1_path}one.php"One</a></li>
            <li><a href="{$galette_base_path}{$galette_galette_test1_path}two.php">Two</a></li>
        </ul>
{/if}
