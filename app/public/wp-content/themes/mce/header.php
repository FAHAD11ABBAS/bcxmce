<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MC Executors Finland</title>
    <?php wp_head(); ?>
</head>

<body>

    <header id="header">
        <a href="/" title="Etusivu">
            <img id="middle-logo"src="<?= get_template_directory_uri() . '/exe-logo-all-black-with-text.png' ?>" alt="">
        </a>
        <input id="menu-toggle" type="checkbox" />
        <label class='menu-button-container' for="menu-toggle">
            <div class='menu-button'></div>
        </label>
        <div class="overlay"></div>
        <nav id="navi">
            <?php wp_nav_menu(['theme_location' => 'primary']); ?>
        </nav>
    </header>

</body>

</html>