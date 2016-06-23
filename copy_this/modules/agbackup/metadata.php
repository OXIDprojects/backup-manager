<?php
$aModule = array(
    'id'           => 'agbackup',
    'title'        => 'Backup Manager',
    'description'  => 'Verwalten Sie automatische Backups Ihrer Seite einfach &uuml;ber das Oxid Backend',
    'thumbnail'    => '',
    'version'      => '1.0',
    'author'       => 'Aggrosoft',
    'extend'      => array(
        
    ),
    'files'        => array(
        'backups' => 'agbackup/controllers/admin/backups.php'
    ),
    'templates'    => array(
        'backups.tpl'  => 'agbackup/out/admin/tpl/backups.tpl'
    )
    
);