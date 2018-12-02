<?php if (!isset($pageid)) { $pageid = ''; } ?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>AOC Bench</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.0.0-alpha.6/css/bootstrap.min.css">

    <link href="style.css" rel="stylesheet">

    <!-- Bootstrap core JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tether/1.4.0/js/tether.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.0/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.16.0/jquery.validate.min.js"></script>
  </head>


  <body>
        <nav class="navbar navbar-toggleable-md navbar-inverse fixed-top bg-inverse">
      <button class="navbar-toggler navbar-toggler-right hidden-lg-up" type="button" data-toggle="collapse" data-target="#navbar" aria-controls="navbarsExampleDefault" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <a class="navbar-brand" href="/">AOC Benchmarks</a>

      <div class="collapse navbar-collapse" id="navbar">
        <ul class="navbar-nav mr-auto">
          <li class="nav-item">
            <a class="nav-link <?=($pageid == 'index' ? 'active' : '');?>" href=".">Results</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?=($pageid == 'hardware' ? 'active' : '');?>" href="hardware.php">Hardware</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?=($pageid == 'raw' ? 'active' : '');?>" href="raw.php">Raw Data</a>
          </li>
        </ul>
        <div class="navbar-nav">
        </div>
      </div>
    </nav>

    <div class="container-fluid">
      <div class="row">
        <main class="col-sm-12 pt-3">
            <div class="container">
