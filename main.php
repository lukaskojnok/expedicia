<?php
$query = $db->prepare( "SELECT * FROM admins" );
$query->execute();
$results = $query->rowCount() ? $query->fetchAll( PDO::FETCH_ASSOC ) : [];

printf("<pre>%s</pre>", print_r( $results , true));
?>