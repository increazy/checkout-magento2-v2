<?php

namespace Increazy\CheckoutV2\Block\Info;

class Info extends \Magento\Payment\Block\Info
{
    public function getSpecificInformation()
    {
        $info = $this->getInfo()->getAdditionalInformation();
        return $info['infos']['response'];
    }

    public function toPdf()
    {
        $this->setTemplate('Increazy_CheckoutV2::info/pdf/info.phtml');
        return $this->toHtml();
    }

    public function toHtml()
    {
        $info = (array)$this->getInfo()->getAdditionalInformation();
        $data = $info['infos'];

        if (!isset($data['pay_method'])) {
            return '<p>Cliente ainda finalizando o pagamento</p>';
        }

        $method = $this->getMethodLabel($data['pay_method']);

        $lines = '';

        if (isset($data['order'])) {
            $lines .= $this->createLine('ID da transação', $data['order']);
        }

        if (isset($data['internal_order']['details'])) {
            if (isset($data['internal_order']['details']['document'])) {
                $lines .= $this->createLine('Documento', $data['internal_order']['details']['document']);
            }
        }

        if (isset($data['response']['url'])) {
            $url = $data['response']['url'];

            $lines .= $this->createLine('URL', "<a href='$url' target='_blank'>Acessar</a>");

            if (strtolower($method) === 'pix') {
                $lines .= $this->createLine('QrCode', "<img src='$url' style='width:200px;height:200px'/>");
            }

            if (isset($data['response']['expiration'])) {
                $lines .= $this->createLine('Vencimento', $data['response']['expiration']);
            }
        }

        if (isset($data['internal_order']['details'])) {
            if (isset($data['internal_order']['details']['card'])) {
                $lines .= $this->createLine('4 últimos dígitos', $data['internal_order']['details']['card']);
            }
        }

        if (isset($data['internal_order']['details'])) {
            if (isset($data['internal_order']['details']['brand'])) {
                $lines .= $this->createLine('Bandeira', $data['internal_order']['details']['brand']);
            }
        }

        if (isset($data['internal_order']['details'])) {
            if (isset($data['internal_order']['details']['installments'])) {
                $lines .= $this->createLine('Parcelamento', $data['internal_order']['details']['installments']);
            }
        }

        if (isset($data['custom_inputs'])) {
            if (count($data['custom_inputs']) > 0) {
                $lines .= '<tr><th colspan="2" style="font-size:18px;text-align:center;padding: 16px 0px;"><b>Campos adicionais</b></th></tr>';
            }

            foreach ($data['custom_inputs'] as $key => $value) {

                if (substr($value, 0, 4) === "http") {
                    $lines .= $this->createLine($key, "<a href='$value' target='_blank'>Ver</a>");
                } else if (substr($value, 0, 1) === "#" && strlen($value) < 8) {
                    $lines .= $this->createLine($key, "<span style='background: $value;width: 16px;height:16px;border-radius:50vh;display:inline-block;'></span> $value");
                } else {
                    $lines .= $this->createLine($key, $value);
                }
            }
        }

        return $this->generateHTML([
            'method'  => $method,
            'lines'   => $lines,
            'gateway' => $data['id'],
        ]);
    }

    private function generateHTML($infos)
    {
        $html = file_get_contents(__DIR__.'/./table.html');

        foreach ($infos as $name => $value) {
            $html = str_replace("{{$name}}", $value, $html);
        }

        return $html;
    }

    private function createLine($label, $value)
    {
        return "<tr><th>$label</th><td>$value</td></tr>";
    }

    private function getMethodlabel($method)
    {
        $method = str_replace('increazy-', '', $method);

        if ($method == 'creditcard')
            return "Cartão de crédito";
        else if ($method == 'onetap')
            return "Cartão de crédito (1 clique)";
        else if ($method == 'debitcard')
            return "Cartão de débito";
        else if ($method == 'free')
            return "Grátis";
        else if ($method == 'billet')
            return 'Boleto';
        else if ($method == 'pix')
            return 'Pix';

        return 'Outro';
    }
}