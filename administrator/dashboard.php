<?php
require_once dirname(__DIR__).'/includes/auth.php';
require_role('administrator');
redirect('admin/dashboard.php');
