<?php
/*
START TRANSACTION;

DELETE FROM controls_logs;
DELETE FROM orders_items;
DELETE FROM orders;

COMMIT;

ALTER TABLE controls_logs AUTO_INCREMENT = 1;
ALTER TABLE orders_items AUTO_INCREMENT = 1;
ALTER TABLE orders AUTO_INCREMENT = 1;







UPDATE orders
SET
  status_vyskladnenie = 'ukoncene',
  status_expedicia = 'ukoncene'
WHERE doprava_typ IS NULL;


*/
