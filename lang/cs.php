<?php
return [
    'title' => 'BackUP — migrace nebo záloha webu',
    'source_site_path' => 'Source site path (absolute)',
    'target_site_path' => 'Target site path (absolute) — optional',
    'database' => 'Database',
    'host' => 'Host',
    'port' => 'Port',
    'user' => 'User',
    'password' => 'Password',
    'database_name' => 'Database name',
    'sftp_section' => 'SFTP (optional, only if Target path not provided)',
    'sftp_auth_password' => 'Password',
    'sftp_auth_key' => 'Key (paste or upload)',
    'sftp_password_placeholder' => 'SFTP password',
    'private_key_notice' => 'Uploaded key is read and removed immediately; it will not be stored.',
    'run_button' => 'Run backup / migrate',
    'target_db_section_note' => 'If these are provided, a dump of the target DB will be included in the final backup.'
];
