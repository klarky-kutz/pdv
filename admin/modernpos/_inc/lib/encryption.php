<?php
class Encryption
{
	protected $method = 'aes-128-cbc';

	protected $options = 0;

	protected $iv = '1991991119919911';
	/**
     * 
     *
     * @param	string	$key
	 * @param	string	$value
	 * 
	 * @return	string
     */	
	public function encrypt($key, $value)
	{
		return strtr(encode_data(openssl_encrypt((string) $value, $this->method, hash('sha256', $key, true), $this->options, $this->iv)), '+/=', '-_,');
	}
	
	/**
     * 
     *
     * @param	string	$key
	 * @param	string	$value
	 * 
	 * @return	string
     */
	public function decrypt($key, $value)
	{
		return trim(openssl_decrypt(decode_data(strtr((string) $value, '-_,', '+/=')), $this->method, hash('sha256', $key, true), $this->options, $this->iv));
	}
}