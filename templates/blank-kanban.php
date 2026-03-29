<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #f4f5f7;
            min-height: 100vh;
        }
        #cwds-kanban-page-wrap {
            padding: 80px 25px 25px 25px;
            min-height: 100vh;
            box-sizing: border-box;
        }
        /* Hide any WP admin bar overlap */
        html.wp-toolbar #cwds-kanban-page-wrap {
            padding-top: 112px; /* 80 + 32 admin bar */
        }
    </style>
</head>
<body <?php body_class('cwds-kanban-page'); ?>>
<?php wp_body_open(); ?>

<div id="cwds-kanban-page-wrap">
    <?php
    while (have_posts()) :
        the_post();
        the_content();
    endwhile;
    ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
