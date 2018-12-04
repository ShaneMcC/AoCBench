<?php if (!isset($pageid)) { $pageid = ''; } ?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>AoCBench</title>
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>

    <link href="style.css" rel="stylesheet">
  </head>


  <body>
    <header>
      <nav class="navbar navbar-expand-md navbar-dark fixed-top bg-dark">
        <a class="navbar-brand" href="#">AoCBench</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarCollapse">
          <ul class="navbar-nav mr-auto">
            <li class="nav-item <?=($pageid == 'index' ? 'active' : '');?>">
              <a class="nav-link " href=".">Results</a>
            </li>
            <li class="nav-item <?=($pageid == 'matrix' ? 'active' : '');?>">
              <a class="nav-link" href="matrix.php">Output Matrix</a>
            </li>
            <li class="nav-item <?=($pageid == 'hardware' ? 'active' : '');?>">
              <a class="nav-link" href="hardware.php">Hardware</a>
            </li>
            <li class="nav-item <?=($pageid == 'raw' ? 'active' : '');?>">
              <a class="nav-link" href="raw.php">Raw Data</a>
            </li>
            <li class="nav-item <?=($pageid == 'rawMatrix' ? 'active' : '');?>">
              <a class="nav-link" href="rawMatrix.php">Matrix Raw Data</a>
            </li>
          </ul>
        </div>
        <?php if (!empty($leaderboardID) && !empty($leaderboardYear)) { ?>
          <div class="navbar-nav">
            <ul class="navbar-nav mr-auto">
              <li class="nav-item">
                <a class="nav-link " href="https://adventofcode.com/<?=$leaderboardYear;?>/leaderboard/private/view/<?=$leaderboardID;?>">Leaderboard</a>
              </li>
            </ul>
          </div>
        <?php } ?>
      </nav>
    </header>

    <main role="main">
      <div class="container-fluid">
        <div class="row">
            <div class="container<?=(isset($fluid) && $fluid ? '-fluid' : '');?>">
