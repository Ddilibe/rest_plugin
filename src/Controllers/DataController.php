<?php

namespace SRC\Controllers;

use SRC\Config\Config;
use SRC\Utils\money;
use SRC\Utils\Certificate;

use WP_Error;
use WP_REST_REQUEST;


define('CISON_CURRENT_YEAR', (int) date('Y'));
define('CISON_CERT_TABLE', Config::get('CISON_CERT_TABLE'));

class DataController {
    public static function allUsers() {
        global $wpdb;
        
        $users = $wpdb->get_results("SELECT * FROM users", ARRAY_A);
        $toSend = array();

        foreach ($users as $user) {
            $userID = $user['ID'];

            if (!function_exists('bp_get_profile_field_data')) {
                continue;
            }

            $name_1 = bp_get_profile_field_data([
               'field' => 1,
               'user_id' => $userID
            ]);
            $name_3 = bp_get_profile_field_data([
               'field' => 3,
               'user_id' => $userID
            ]);
            $name_873 = bp_get_profile_field_data([
               'field' => 873,
               'user_id' => $userID
            ]);
            $name_877 = bp_get_profile_field_data([
               'field' => 877,
               'user_id' => $userID
            ]);
            $name_6 = bp_get_profile_field_data([
               'field' => 6,
               'user_id' => $userID
            ]);
            $name_557 = bp_get_profile_field_data([
               'field' => 557,
               'user_id' => $userID
            ]);
            $name_561 = bp_get_profile_field_data([
               'field' => 561,
               'user_id' => $userID
            ]);
            $name_5 = bp_get_profile_field_data([
               'field' => 5,
               'user_id' => $userID
            ]);
            $name_1595 = bp_get_profile_field_data([
               'field' => 1595,
               'user_id' => $userID
            ]);

            $name_917 = bp_get_profile_field_data([
               'field' => 917,
               'user_id' => $userID
            ]);
            $name_22 = bp_get_profile_field_data([
               'field' => 22,
               'user_id' => $userID
            ]);
            $name_888 = bp_get_profile_field_data([
               'field' => 888,
               'user_id' => $userID
            ]);

     //     $name_
     //     21 = 21
     //        $name_276 = 276
     //        $name_25 = 25
     //        $name_24 = 24
     //        $name_23 = 23
     //        $name_1425 = 1425
     //        $name_859 = 859
     //        $name_840 = 840
     //        $name_839 = 839
     //        $name_836 = 836
     //        $name_835 = 835

     //      $name_
     //      2 = 2
     //        $name_538 = 538
     //        $name_864 = 864
     //        $name_894 = 894
     //        $name_876 = 876
     //        $name_874 = 874
     //        $name_875 = 875
     //        $name_863 = 863
     //        $name_862 = 862
     //        $name_860 = 860
     //        $name_838 = 838

     //    $name_
     //    861 = 861
     //        $name_575 = 575
     //        $name_578 = 578
     //        $name_579 = 579
     //        $name_574 = 574
     //        $name_576 = 576
     //        $name_891 = 891
     //        $name_907 = 907
     //        $name_903 = 903
     //        $name_581 = 581
     //        $name_582 = 582

     //    $name_
     //    580 = 580
     //        $name_1433 = 1433
     //        $name_1434 = 1434
     //        $name_889 = 889
     //        $name_890 = 890
     //        $name_893 = 893
     //        $name_1435 = 1435
     //        $name_1436 = 1436
     //        $name_1437 = 1437
     //        $name_892 = 892
     //        $name_1488 = 1488

     //   $name_
     //   1489 = 1489
     //        $name_1490 = 1490
     //        $name_1491 = 1491
     //        $name_1492 = 1492
     //        $name_1595 = 1595
     //        $name_1494 = 1494
     //        $name_1495 = 1495
     //        $name_1498 = 1498
     //        $name_1497 = 1497
     //        $name_1496 = 1496
     //        $name_908 = 908

     //   $name_
     //   1432 = 1432
     //        $name_1429 = 1429
     //        $name_1430 = 1430
     //        $name_1431 = 1431
     //        $name_1438 = 1438
     //        $name_1439 = 1439
     //        $name_1440 = 1440
     //        $name_1441 = 1441
     //        $name_1442 = 1442
     //        $name_1443 = 1443
     //        $name_1444 = 1444

     //   $name_
     //   1445 = 1445
     //        $name_1446 = 1446
     //        $name_1599 = 1599
     //        $name_1597 = 1597
     //        $name_1450 = 1450
     //        $name_1447 = 1447
     //        $name_1448 = 1448
     //        $name_1449 = 1449
     //        $name_1451 = 1451
     //        $name_1452 = 1452
     //        $name_1453 = 1453

     //   $name_
     //   1454 = 1454
     //        $name_1609 = 1609
     //        $name_1610 = 1610
     //        $name_1500 = 1500
     //        $name_1501 = 1501
     //        $name_1502 = 1502
     //        $name_1503 = 1503
     //        $name_1504 = 1504
     //        $name_1506 = 1506
     //        $name_1507 = 1507
     //        $name_1508 = 1508

     //   $name_
     //   1509 = 1509
     //        $name_1510 = 1510
     //        $name_1455 = 1455
     //        $name_1512 = 1512
     //        $name_1513 = 1513
     //        $name_1514 = 1514
     //        $name_1515 = 1515
     //        $name_1516 = 1516
     //        $name_1518 = 1518
     //        $name_1519 = 1519
     //        $name_1520 = 1520

     //   $name_
     //   1521 = 1521
     //        $name_1522 = 1522
     //        $name_1524 = 1524
     //        $name_1525 = 1525
     //        $name_1526 = 1526
     //        $name_1527 = 1527
     //        $name_1528 = 1528
     //        $name_1530 = 1530
     //        $name_1531 = 1531
     //        $name_1532 = 1532
     //        $name_1533 = 1533

     //   $name_
     //   1534 = 1534
     //        $name_1547 = 1547
     //        $name_1537 = 1537
     //        $name_1540 = 1540
     //        $name_1543 = 1543
     //        $name_1546 = 1546
     //        $name_1535 = 1535
     //        $name_1536 = 1536
     //        $name_1548 = 1548
     //        $name_1487 = 1487
     //        $name_1562 = 1562

     //   $name_
     //   1563 = 1563
     //        $name_1549 = 1549
     //        $name_1552 = 1552
     //        $name_1555 = 1555
     //        $name_1558 = 1558
     //        $name_1561 = 1561
     //        $name_1565 = 1565
     //        $name_1566 = 1566
     //        $name_1567 = 1567
     //        $name_1564 = 1564
     //        $name_1568 = 1568

     //   $name_
     //   1569 = 1569
     //        $name_1571 = 1571
     //        $name_1572 = 1572
     //        $name_1570 = 1570
     //        $name_1574 = 1574
     //        $name_1575 = 1575
     //        $name_1573 = 1573
     //        $name_1577 = 1577
     //        $name_1578 = 1578
     //        $name_1576 = 1576
     //        $name_1580 = 1580

     //   $name_
     //   1581 = 1581
     //        $name_1582 = 1582
     //        $name_1579 = 1579
     //        $name_1583 = 1583
     //        $name_1584 = 1584
     //        $name_1589 = 1589
     //        $name_1590 = 1590
     //        $name_1585 = 1585
     //        $name_1588 = 1588
     //        $name_1591 = 1591
     //        $name_1538 = 1538

     //   $name_
     //   1539 = 1539
     //        $name_1541 = 1541
     //        $name_1542 = 1542
     //        $name_1544 = 1544
     //        $name_1545 = 1545
     //        $name_1493 = 1493


            $single_data = array(
                "user_id" => $userID,
                "first_name" => $name_1,
                "middle_name" => $name_3,
                "last_name" => $name_873,
                "user_login" => $name_877,
                "user_email" => $name_6,
                "joined_date" => $name_557,
                "phone_number" => $name_561,
                "display_name" => $name_5,
                "member_id" => $name_1595,
                "is_transiting" => $name_917
            );

            $toSend[] = $single_data;
        }

        return rest_ensure_response([
            "data" => $toSend,
            "status" => "success"
        ], 200);
    }
}