<?php

require '../vendor/autoload.php';

$repository = $_SERVER['QUERY_STRING'];

if (!preg_match('/^[-\d\w._]+\/[-\d\w._]+$/', $repository)) {
    throw new UnexpectedValueException('Repsitory format not recognised');
}

if (strpos($repository, '..') !== false) {
    throw new UnexpectedValueException('Unexpected values in repository name');
}

$formatLargeNummber = function (int $x) : string {
    if ($x > 1000) {
        $x_number_format = number_format($x);
        $x_array = explode(',', $x_number_format);
        $x_parts = ['K', 'M', 'B', 'T'];
        $x_count_parts = count($x_array) - 1;
        $x_display = $x_array[0] . ((int) $x_array[1][0] !== 0 ? '.' . $x_array[1][0] : '');
        $x_display .= $x_parts[$x_count_parts - 1];

        return $x_display;
    }

    return (string) $x;
};

$pct = Psalm\Shepherd\Api::getHistory($repository);

$config = Psalm\Shepherd\Config::getInstance();

$github_url = $config->gh_enterprise_url ?: 'https://github.com';
?>
<html>
<head>
<title>Shepherd - <?php echo $repository ?></title>
<link rel="stylesheet" href="/assets/styles/main.css?3">
<link rel="preconnect" href="https://fonts.gstatic.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400&family=IBM+Plex+Sans&display=swap" rel="stylesheet">
<meta name="viewport" content="initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<link rel="icon" type="image/png" href="/assets/img/favicon.png">
</head>
<body>
    <nav>
        <div class="container">
            <h1><a href="/">Shepherd</a></h1>
        </div>
    </nav>

    <div class="container front">
        <div class="coverage_list">
            <h2><a href="<?php echo $github_url . '/' . $repository ?>"><?php echo $repository ?></a></h2>

            <p><img src="/github/<?php echo $repository ?>/coverage.svg"> <img src="/github/<?php echo $repository ?>/level.svg"></p>

            <h3>Type coverage history</h3>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Commit</th>
                        <th>Type Coverage</th>
                        <th>Analysed Expression #</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pct as $date => [$commit, $coverage, $total]) : ?>
                    <tr>
                        <td><?php echo date('F j Y, H:i:s T', strtotime($date)) ?></td>
                        <td><a href="<?php echo $github_url . '/' . $repository . '/commit/' . $commit ?>"><?php echo substr($commit, 0, 7) ?></a></td>
                        <td><?php echo number_format($coverage, 3) ?>%</td>
                        <td><?php echo $formatLargeNummber($total) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

