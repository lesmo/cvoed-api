<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \SimpleHtmlDom\simple_html_dom;

function utf8ize($d) {
    if (is_array($d)) {
        foreach ($d as $k => $v) {
            $d[$k] = utf8ize($v);
        }
    } else if (is_string ($d)) {
        return utf8_encode($d);
    }
    return $d;
}

$app->get('/', function (Request $req, Response $res) {
    $params = $req->getQueryParams();
    $query = isset($params['q']) ? $params['q'] : false;
    if ( !$query || strlen($query) < 4 )
        return $res->withStatus(400)->withJson([
            'error' => 'query',
            'data' => []
        ]);
    
    // Bajar y parsear HTML de CVOED
    $cvoed_curl = curl_init('http://cvoed.imss.gob.mx/COED/home/normativos/DPM/censo/reportes/busqueda2.php');
    curl_setopt($cvoed_curl, CURLOPT_POST, 1);
    curl_setopt($cvoed_curl, CURLOPT_POSTFIELDS, http_build_query(['palabras_busqueda' => $query]));
    curl_setopt($cvoed_curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($cvoed_curl, CURLOPT_HEADER, 0);
    curl_setopt($cvoed_curl, CURLOPT_RETURNTRANSFER, 1);
    
    $cvoed_html = curl_exec($cvoed_curl);
    if ( !$cvoed_html )
        return $res->withStatus(503)->withJson([
            'error' => 'cvoed.server',
            'data' => []
        ]);
    
    // Arreglar etiquetas malhechas del listado
    $cvoed_html = str_replace('<b><a href="#">', '<a href="#"><b>', $cvoed_html);
        
    $cvoed = new simple_html_dom();
    $cvoed -> load($cvoed_html);
        
    // Sacamos los resultados
    $cvoed_items = $cvoed -> find('#busquedatexto > a');
    $cvoed_response = [];
    
    $s = 0;
    foreach ($cvoed_items as $i => $cvoed_item) {
        $cvoed_item_b = $cvoed_item -> children(0);
        $cvoed_item_span = $cvoed -> find('#busquedatexto > span', $s);
        $s += 2;
        
        if ( !$cvoed_item_b || !$cvoed_item_span )
            continue;
        
        // Preparar nombre bonito
        $cvoed_item_name_cvoed = trim(preg_replace('/(\s+|&nbsp;)/', ' ',$cvoed_item_b->innertext));
        $cvoed_item_name_cvoed = preg_replace('/\s+/', ' ', $cvoed_item_name_cvoed);
        
        $cvoed_item_name = ucwords(strtolower(trim($cvoed_item_name_cvoed)));
        $cvoed_item_name_split = explode(' ', $cvoed_item_name, 3);

        if ( count($cvoed_item_name_split) > 2 ) {
            $cvoed_item_name_detail = [
                'first' => $cvoed_item_name_split[2],
                'last'  => $cvoed_item_name_split[0] . ' ' . $cvoed_item_name_split[1],
                'full'  => $cvoed_item_name_split[2] . ' ' . $cvoed_item_name_split[0] . ' ' . $cvoed_item_name_split[1],
                'cvoed' => $cvoed_item_name_cvoed
            ];
        }

        // Preparar hospital bonito
        $cvoed_item_hospital_text = explode('</b>', $cvoed_item->innertext);
        if ( count($cvoed_item_hospital_text) > 1 )
            $cvoed_item_hospital_text = trim($cvoed_item_hospital_text[1]);
        else
            $cvoed_item_hospital_text = '';

        $cvoed_item_hospital_text = preg_replace('/(\s+|&nbsp;)/', ' ', $cvoed_item_hospital_text);
        $cvoed_item_hospital_text = preg_replace('/\s+/', ' ', $cvoed_item_hospital_text);
        $cvoed_item_hospital_text = strtolower($cvoed_item_hospital_text);
        $cvoed_item_hospital_split = explode('/', $cvoed_item_hospital_text, 3);

        $cvoed_item_hospital_detail = [
            'institution' => strtoupper(trim($cvoed_item_hospital_split[0])),
            'zone' => ucwords(trim($cvoed_item_hospital_split[1])),
            'name' => ucwords(trim($cvoed_item_hospital_split[2])),
            'cvoed' => strtoupper($cvoed_item_hospital_text)
        ];

        // Agregar a respuesta
        $cvoed_response[] = [
            'name' => $cvoed_item_name_detail,
            'hospital' => $cvoed_item_hospital_detail,
            'date' => preg_replace('/\s+\-\s+$/', '', $cvoed_item_span->plaintext)
        ];
    }
    
    return $res->withJson([
        'error' => null,
        'query' => $query_metaphone,
        'data' => $cvoed_response
    ]);
});