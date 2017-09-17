{if $login->isLogged()}
        <h1 class="nojs">Test 1</h1>
        <ul>
            <li><a href="{base_url}/{$galette_galette_test1_path}one.php"One</a></li>
            <li><a href="{base_url}/{$galette_galette_test1_path}two.php">Two</a></li>
        </ul>
{/if}
