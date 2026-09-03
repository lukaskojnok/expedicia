<?php
/*
2026001428
0657570016381120260902


2026001503
Z1656710543, Z2244176733




START TRANSACTION;

DELETE FROM controls_logs;
DELETE FROM orders_items;
DELETE FROM orders;
DELETE FROM 	order_update_logs;

COMMIT;

ALTER TABLE controls_logs AUTO_INCREMENT = 1;
ALTER TABLE orders_items AUTO_INCREMENT = 1;
ALTER TABLE orders AUTO_INCREMENT = 1;
ALTER TABLE order_update_logs AUTO_INCREMENT = 1;







UPDATE orders
SET
  status_vyskladnenie = 'ukoncene',
  status_expedicia = 'ukoncene'
WHERE doprava_typ IS NULL;


*/
