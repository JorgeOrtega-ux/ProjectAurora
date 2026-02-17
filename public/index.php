<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="assets/css/styles.css">
    <link rel="stylesheet" type="text/css" href="assets/css/components/components.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <title>Project Aurora</title>
</head>

<body>
    <div class="page-wrapper">
        <div class="main-content">
            <div class="general-content">
                <div class="general-content-top">
                    <?php 
                    // __DIR__ es '.../public'. Subimos un nivel (/../) para llegar a 'includes'
                    include __DIR__ . '/../includes/layout/header.php'; 
                    ?>
                </div>

                <div class="general-content-bottom">
                    <?php 
                    include __DIR__ . '/../includes/modules/moduleSurface.php'; 
                    ?>
                    <div class="general-content-scrolleable"></div>
                </div>
            </div>
        </div>
    </div>

    <script type="module" src="assets/js/app-init.js"></script>
</body>

</html>