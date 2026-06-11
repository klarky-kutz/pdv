<?php
function get_pmethods($data = array()) 
{
	$model = registry()->get('loader')->model('pmethod');
	return $model->getPmethods($data);
}

function get_the_pmethod($id, $field = null)
{
	$model = registry()->get('loader')->model('pmethod');
	$pmethods = $model->getPmethod($id);
	if ($field && isset($pmethods[$field])) {
		return $pmethods[$field];
	} elseif ($field) {
		return null; // it's equivalent to return;
	}
	return '';
}

/**
 * Retorna a lista padrão de IDs de métodos de pagamento
 * usados automaticamente na criação de novas lojas.
 *
 * Aqui usamos diretamente os IDs canônicos da instalação:
 *  1 = Cash on Delivery (cod)
 *  2 = Bkash (bkash)
 *  3 = Gift card (gift_card)
 *  4 = Credit (credit)
 *  5 = Cartão de Crédito (card_credit)
 *  6 = Pix (pix)
 *  7 = Dinheiro (cash)
 *  9 = Cartão de Débito (card_debit)
 */
function get_default_pmethod_ids()
{
	// 1=cod, 3=gift_card, 4=credit, 5=card_credit, 6=pix, 7=cash, 9=card_debit
	// 2 (bkash) foi removido dos padrões
	return array(1, 3, 4, 5, 6, 7, 9);
}
