<?php
namespace Zidisha\Payment;


abstract class PaymentService
{
    abstract public function makePayment(Payment $payment, $data = []);
}
