<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Compare Databases</title>
    <link rel="stylesheet" href="resources/bootstrap-4.4.1-dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-3 mb-3">
    <?php

    if (!file_exists('.env.json')) {
        ?>
        <div class="row">
            <div class="col-12">
                <p class="alert alert-danger">Please, create a .env.json file</p>
            </div>
        </div>
        <?php
        die();
    }

    $env = json_decode(file_get_contents('.env.json'));

    $conn1 = new mysqli($env->database1->host, $env->database1->user, $env->database1->pass, $env->database1->name);
    if ($conn1->connect_error) {
        ?>
        <div class="row">
            <div class="col-12">
                <p class="alert alert-danger">Connection failed with <?= $env->database1->name ?>.</p>
                <p>
                    <strong>Log: </strong>
                    <?= $conn1->connect_error ?>
                </p>
            </div>
        </div>
        <?php
        die();
    }

    $conn2 = new mysqli($env->database2->host, $env->database2->user, $env->database2->pass, $env->database2->name);
    if ($conn2->connect_error) {
        ?>
        <div class="row">
            <div class="col-12">
                <p class="alert alert-danger">Connection failed with <?= $env->database2->name ?>.</p>
                <p>
                    <strong>Log: </strong>
                    <?= $conn2->connect_error ?>
                </p>
            </div>
        </div>
        <?php
        die();
    }
    ?>
    <div class="accordion" id="accordion">
        <?php
        $tablesDatabase1       = [];
        $resultTablesDatabase1 = $conn1->query("SHOW TABLES;");
        while ($row = $resultTablesDatabase1->fetch_array()) {
            array_push($tablesDatabase1, $row[0]);
        }

        $tablesDatabase2       = [];
        $resultTablesDatabase2 = $conn2->query("SHOW TABLES;");
        while ($row = $resultTablesDatabase2->fetch_array()) {
            array_push($tablesDatabase2, $row[0]);
        }

        foreach ($tablesDatabase1 as $key => $tableDatabase1) {
            $database2Table = array_search($tableDatabase1, $tablesDatabase2);

            if ($database2Table === false) {
                ?>
                <div class="card">
                    <div class="card-header bg-danger text-white" data-toggle="collapse" data-target="#collapse<?= $tableDatabase1 ?>" aria-expanded="true" aria-controls="collapse<?= $tableDatabase1 ?>">
                        <?= $tableDatabase1 ?>
                    </div>
                    <div id="collapse<?= $tableDatabase1 ?>" class="collapse" data-parent="#accordion">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    The <strong><?= $tableDatabase1 ?></strong> table does not exists in <strong><?= $env->database2->name ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                unset($tablesDatabase1[$key]);
                continue;
            }


            $columnsTableDatabase1       = [];
            $resultColumnsTableDatabase1 = $conn1->query("DESCRIBE $tableDatabase1;");
            while ($row = $resultColumnsTableDatabase1->fetch_assoc()) {
                array_push($columnsTableDatabase1, $row);
            }

            $columnsTableDatabase2       = [];
            $resultColumnsTableDatabase2 = $conn2->query("DESCRIBE $tableDatabase1;");
            while ($row = $resultColumnsTableDatabase2->fetch_assoc()) {
                array_push($columnsTableDatabase2, $row);
            }

            $tableComparison = "";
            foreach ($columnsTableDatabase1 as $key => $columnTableDatabase1) {
                $indexCollumn = array_search($columnTableDatabase1["Field"], array_column($columnsTableDatabase2, "Field"));

                if ($indexCollumn === false) {
                    $tableComparison .= "
                        <tr class=\"table-danger\">
                            <td><strong>" . $columnTableDatabase1["Field"] . "</strong></td>
                            <td>Exists</td>
                            <td>Does not exist</td>
                        </tr>";
                        continue;
                }

                $columnTableDatabase2 = $columnsTableDatabase2[$indexCollumn];

                $columnDiferences = false;
                if ($columnTableDatabase1["Type"] != $columnTableDatabase2["Type"]) {
                    $tableComparison .= "
                        <tr class=\"table-warning\">
                            <td><strong>" . $columnTableDatabase1["Field"] . "</strong></td>
                            <td>" . $columnTableDatabase1["Type"] . "</td>
                            <td>" . $columnTableDatabase2["Type"] . "</td>
                        </tr>";
                        $columnDiferences = true;
                }
                if ($columnTableDatabase1["Null"] != $columnTableDatabase2["Null"]) {
                    $tableComparison .= "
                        <tr class=\"table-warning\">
                            <td><strong>" . $columnTableDatabase1["Field"] . "</strong></td>
                            <td>" . ($columnTableDatabase1["Null"] == "YES" ? "Nullable" : "Required") . "</td>
                            <td>" . ($columnTableDatabase2["Null"] == "YES" ? "Nullable" : "Required") . "</td>
                        </tr>";
                        $columnDiferences = true;
                }
                if ($columnTableDatabase1["Default"] != $columnTableDatabase2["Default"]) {
                    $tableComparison .= "
                        <tr class=\"table-warning\">
                            <td><strong>" . $columnTableDatabase1["Field"] . "</strong></td>
                            <td>Default value: " . $columnTableDatabase1["Default"] . "</td>
                            <td>Default value: " . $columnTableDatabase2["Default"] . "</td>
                        </tr>";
                        $columnDiferences = true;
                }

                if (!$columnDiferences) {
                    $tableComparison .= "
                        <tr>
                            <td><strong>" . $columnTableDatabase1["Field"] . "</strong></td>
                            <td>Not changed</td>
                            <td>Not changed</td>
                        </tr>";
                }
            }

            $resultAutoincrementTable1 = $conn1->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$env->database1->name}' AND TABLE_NAME = '{$tableDatabase1}';");
            $resultAutoincrementTable2 = $conn1->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$env->database2->name}' AND TABLE_NAME = '{$tableDatabase1}';");
            $autoincrementTable1 = $resultAutoincrementTable1->fetch_assoc()['AUTO_INCREMENT'];
            $autoincrementTable2 = $resultAutoincrementTable2->fetch_assoc()['AUTO_INCREMENT'];

            $resultNumRows1 = $conn1->query("SELECT COUNT(*) AS quantidade FROM `{$env->database1->name}`.`{$tableDatabase1}`;");
            $resultNumRows2 = $conn1->query("SELECT COUNT(*) AS quantidade FROM `{$env->database2->name}`.`{$tableDatabase1}`;");
            $numRows1 = $resultNumRows1->fetch_assoc()['quantidade'];
            $numRows2 = $resultNumRows2->fetch_assoc()['quantidade'];

            $cardClass = "";
            if ($autoincrementTable1 != $autoincrementTable2) {
                $cardClass = "bg-info text-white";
            }
            if ($numRows1 != $numRows2) {
                $cardClass = "bg-primary text-white";
            }
            if (strpos($tableComparison, "class=\"table-warning\"")) {
                $cardClass = "bg-warning text-white";
            }
            if (strpos($tableComparison, "class=\"table-danger\"")) {
                $cardClass = "bg-danger text-white";
            }
            ?>
            <div class="card">
                <div class="card-header <?= $cardClass ?>" data-toggle="collapse" data-target="#collapse<?= $tableDatabase1 ?>" aria-expanded="true" aria-controls="collapse<?= $tableDatabase1 ?>">
                    <?= $tableDatabase1 ?>
                </div>
                <div id="collapse<?= $tableDatabase1 ?>" class="collapse" data-parent="#accordion">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <h3>Auto increment</h3>
                            </div>
                            <div class="col-6">
                                <strong><?= $env->database1->name ?>: </strong><span class="<?= $autoincrementTable1 != $autoincrementTable2 ? "text-danger" : "text-success" ?>"><?= $autoincrementTable1 ?></span>
                            </div>
                            <div class="col-6">
                                <strong><?= $env->database2->name ?>: </strong><span class="<?= $autoincrementTable1 != $autoincrementTable2 ? "text-danger" : "text-success" ?>"><?= $autoincrementTable2 ?></span>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-12">
                                <h3>Num rows</h3>
                            </div>
                            <div class="col-6">
                                <strong><?= $env->database1->name ?>: </strong><span class="<?= $numRows1 != $numRows2 ? "text-danger" : "text-success" ?>"><?= $numRows1 ?></span>
                            </div>
                            <div class="col-6">
                                <strong><?= $env->database2->name ?>: </strong><span class="<?= $numRows1 != $numRows2 ? "text-danger" : "text-success" ?>"><?= $numRows2 ?></span>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-12">
                                <h3>Columns comparison</h3>
                            </div>
                            <div class="col-12">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Column</th>
                                            <th><?= $env->database1->name ?></th>
                                            <th><?= $env->database2->name ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?= $tableComparison ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        ?>
        </div>
    </div>

    <script src="resources/jquery.min.js"></script>
    <script src="resources/bootstrap-4.4.1-dist/js/bootstrap.min.js"></script>
</body>
</html>
