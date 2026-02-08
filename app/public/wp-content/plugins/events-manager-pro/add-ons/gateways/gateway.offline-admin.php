<?php
namespace EM\Payments\Offline;

/**
 * This Gateway is slightly special, because as well as providing public static functions that need to be activated, there are offline payment public static functions that are always there e.g. adding manual payments.
 * @author marcus
 */
class Gateway_Admin extends \EM\Payments\Gateway_Admin {}
?>