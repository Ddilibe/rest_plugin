# Membership

```php
$unpaid_product_ids = [];
foreach ($unpaid_fees as $fee) {
    if (isset($fee['product_ids']) && is_array($fee['product_ids'])) {
        foreach ($fee['product_ids'] as $pid) {
            $pid = (int) $pid;
            if ($pid > 0) {
                $unpaid_product_ids[] = $pid;
            }
        }
    } elseif (isset($fee['product_id'])) {
        $pid = (int) $fee['product_id'];
        if ($pid > 0) {
            $unpaid_product_ids[] = $pid;
        }
    }
}

foreach ($unpaid_fees as $fee) {
        if (isset($fee['product_ids']) && is_array($fee['product_ids'])) {
            foreach ($fee['product_ids'] as $pid) {
                $pid = (int) $pid;
                if ($pid > 0) {
                    $unpaid_product_ids[] = $pid;
                }
            }
        } elseif (isset($fee['product_id'])) {
            $pid = (int) $fee['product_id'];
            if ($pid > 0) {
                $unpaid_product_ids[] = $pid;
            }
        }
    }
foreach ($required_fees as $key => $fee) {
        if (empty($paid_fees[$key]){
                        $unpaid_product_ids[] = $fee;
                }
    }

    is_product_in_required_fees

    foreach ($unpaid_fees as $fee) {
        if (isset($fee['product_ids']) && is_array($fee['product_ids'])) {
            $pid = $fee['product_ids'];
            $profile_type = bp_get_user_meta( $user_id, 'bp_profile_type', false );
			if ($profile_type){
				if (strcasecmp($profile_type, "Student Member") === 0){
					$unpaid_product_ids[] = $pid[2];
				} else {
					$unpaid_product_ids[] = $pid[0];
				}
			}
        } elseif (isset($fee['product_id'])) {
            $pid = (int) $fee['product_id'];
            if ($pid > 0 && is_product_in_required_fees($pid, $required_fees)) {
                $unpaid_product_ids[] = $pid;
            }
        }
    }
```