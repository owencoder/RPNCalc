	<?php
		//ini_set( 'display_errors', 1 );
		class CalcEval
		{
			public $calc_source = "";
			public $calc_source_len = 0;
			public $token_position = 0;
			public $arr = array ();

			public function exec ( $str )
			{
				$debugMode = false;
				$ToBin = false;
				$ToHex = false;

				self::initializeStack ();
				$this->calc_source = preg_replace('/(\s|　)/','',$str);
				if ( strpos ( $this->calc_source, "--debug" ) !== false )
				{
					$debugMode = true;
					$this->calc_source = str_replace ( "--debug", "", $this->calc_source );
				}

				if ( strpos ( $this->calc_source, "-b" ) !== false )
				{
					$ToBin = true;
					$this->calc_source = str_replace ( "-b", "", $this->calc_source );
				}

				if ( strpos ( $this->calc_source, "-h" ) !== false )
				{
					$ToHex = true;
					$this->calc_source = str_replace ( "-h", "", $this->calc_source );
				}

				$this->calc_source = str_replace ( "PI", M_PI, $this->calc_source );
				$this->calc_source = str_replace ( "π", M_PI, $this->calc_source );
				$this->calc_source = str_replace ( "E", M_E, $this->calc_source );
				$this->calc_source = str_replace ( "EULER", M_EULER, $this->calc_source );
				//$this->calc_source = str_replace ( "PI", M_PI, $this->calc_source );

				$this->calc_source_len = strlen ( $this->calc_source );
				$this->token_position = 0;

				$buffer = "";
				$dest = "";

				for ( ;; )
				{
					$token = self::getToken ();
					//	終わった？
					if ( $token === null )
					{
						//	スタックが空になるまでポップ
						for ( ;; )
						{
							$tmp = self::pop ();
							if ( $tmp === null )
							{
								break;
							}
							$buffer .= $tmp." ";
						}
						break;
					}

					//	文字が数値か？
					if ( is_numeric ( $token ) )
					{
						$buffer .= $token." ";
						continue;
					} else {
						if ( self::isFunction ( $token ) )
						{
							$isNowFunction = true;
						}
					}

					//	かっこ終わりです
					if ( $token === ")" )
					{
						for ( ;; )
						{
							$tmp = self::pop ();
							if ( $tmp === null )
							{
								//	おい
								break;
							}
							if ( $tmp === "(" )
							{
								break;
							}
							$buffer .= $tmp." ";
						}
						continue;
					}

					//	括弧始まったのでスタックに追加
					if ( $token === "(" )
					{
						self::push ( $token );
						continue;
					}

					for ( ;; )
					{
						//	スタックが空かどうかチェック。なければ
						if ( self::isEmptyStack () )
						{
							self::push ( $token );
							break;
						}

						//	関数・かけ算割り算の優先度を判定
						if ( self::isLowerPriority ( $token ) )
						{
							$buffer .= self::pop () ." ";
							continue;
						} else {
							self::push ( $token );
							break;
						}
					}
				}

				$dest = $buffer;
				if ( $debugMode )
				{
					return $dest;
				} else {
					$result = self::repol ( $dest );
					if ( $result === false )
					{
						return false;
					}

					$result = floor($result * 1000000) / 1000000;
					if ( $ToBin )
					{
						return decbin ( $result );
					}
					if ( $ToHex )
					{
						return dechex ( $result );
					}

					$va = self::float2div ( $result );
					if ( $va === $result )
					{
						return $result;
					} else {
						return $result ."(". $va .")";
					}
				}
			}

			function repol ( $str )
			{
				self::initializeStack ();
				$len = strlen ( $str );
				$data = explode ( " ", trim ( $str ) );
				$x = 0;
				$y = 0;
				$stack = array ();

				foreach ( $data as $value )
				{
					if ( $value === "+" )
					{
						self::push ( self::pop() + self::pop () );
						continue;
					}

					if ( $value === "-" )
					{
						$a = self::pop (); $b = self::pop ();
						self::push ( $b - $a );
						continue;
					}

					if ( $value === "*" )
					{
						self::push ( self::pop() * self::pop () );
						continue;
					}

					if ( $value === "/" )
					{
						$a = self::pop (); $b = self::pop ();
						if ( $a == 0 )
							{ break; }
						self::push ( $b / $a );
						continue;
					}

					if ( $value === "%" )
					{
						$a = self::pop (); $b = self::pop ();
						if ( $a == 0 )
							{ break; }
						self::push ( $b % $a );
						continue;
					}

					if ( $value === "^" )
					{
						$a = self::pop (); $b = self::pop ();
						//	ctype_digit = 文字列に数字だけあるかどうか。数字だけ = intだと仮定する
						if ( ctype_digit ( $a ) && ctype_digit ( $b ) )
						{
							self::push ( (int)$b ^ (int)$a );
						} else {
							self::push ( pow ( $b, $a ) );
						}
						continue;
					}


					if ( $value === "&" )
					{
						$a = self::pop (); $b = self::pop ();
						self::push ( $b & $a );
						continue;
					}

					//	特殊関数
					if ( $value === "lx" )
					{
						$x = self::pop ();
						self::push ( 0 );
						continue;
					}

					if ( $value === "ly" )
					{
						$y = self::pop ();
						self::push ( 0 );
						continue;
					}

					if ( $value === "sx" )
					{
						self::push ( $x );
						continue;
					}

					if ( $value === "sy" )
					{
						self::push ( $y );
						continue;
					}

					if ( $value === "l" )
					{
						$stack[self::pop()] = self::pop ();
						self::push ( 0 );
						continue;
					}

					if ( $value === "s" )
					{
						$a = self::pop ();
						if ( !isset ( $stack[$a] ) )
						{
							self::push ( 0 );
						} else {
							self::push ( $stack[$a] );
						}
						continue;
					}

					if ( $value === "cos" )
					{
						//
						self::push ( cos ( self::pop () ) );
						continue;
					}

					if ( $value === "sin" )
					{
						//
						self::push ( sin ( self::pop () ) );
						continue;
					}

					if ( $value === "tan" )
					{
						//
						self::push ( tan ( self::pop () ) );
						continue;
					}

					if ( $value === "acos" )
					{
						//
						self::push ( acos ( self::pop () ) );
						continue;
					}

					if ( $value === "asin" )
					{
						//
						self::push ( asin ( self::pop () ) );
						continue;
					}

					if ( $value === "atan" )
					{
						//
						self::push ( atan ( self::pop () ) );
						continue;
					}

					if ( $value === "cosh" )
					{
						//
						self::push ( cosh ( self::pop () ) );
						continue;
					}

					if ( $value === "sinh" )
					{
						//
						self::push ( sinh ( self::pop () ) );
						continue;
					}

					if ( $value === "tanh" )
					{
						//
						self::push ( tanh ( self::pop () ) );
						continue;
					}

					if ( $value === "acosh" )
					{
						//
						self::push ( acosh ( self::pop () ) );
						continue;
					}

					if ( $value === "asinh" )
					{
						//
						self::push ( asinh ( self::pop () ) );
						continue;
					}

					if ( $value === "atanh" )
					{
						//
						self::push ( atanh ( self::pop () ) );
						continue;
					}

					if ( $value === "deg" )
					{
						//
						self::push ( deg2rad ( self::pop () ) );
						continue;
					}

					if ( $value === "rad" )
					{
						//
						self::push ( rad2deg ( self::pop () ) );
						continue;
					}

					if ( $value === "sqrt" )
					{
						//
						self::push ( sqrt ( self::pop () ) );
						continue;
					}

					if ( $value === "log" )
					{
						self::push ( log ( self::pop () ) );
						continue;
					}

					if ( $value === "pow" )
					{
						self::push ( pow ( self::pop (), 2 ) );
						continue;
					}

					if ( $value === "abs" )
					{
						self::push ( abs ( self::pop () ) );
						continue;
					}

					if ( $value === "floor" )
					{
						self::push ( floor ( self::pop () ) );
						continue;
					}

					if ( $value === "ceil" )
					{
						self::push ( ceil ( self::pop ()  ) );
						continue;
					}

					if ( $value === "rnd" )
					{
						self::push ( mt_rand ( 0, self::pop () ) );
						continue;
					}

					if ( $value === "exp" )
					{
						self::push ( exp ( self::pop () ) );
						continue;
					}

					if ( $value === "round" )
					{
						self::push ( round ( self::pop () ) );
						continue;
					}

					self::push ( $value );
				}

				$result = self::pop ();
				if ( is_numeric ( $result ) )
				{
					return $result;
				} else {
					return false;
				}
			}

			//	使える関数
			function isFunction ( $value )
			{
				if ( $value === "sin" ||
					$value === "cos" ||
					$value === "tan" ||
					$value === "sqrt" ||
					$value === "log" ||
					$value === "pow" ||
					$value === "floor" ||
					$value === "abs" ||
					$value === "ceil" ||
					$value === "rnd" ||
					$value === "atan" ||
					$value === "asin" ||
					$value === "acos" ||
					$value === "atanh" ||
					$value === "asinh" ||
					$value === "acosh" ||
					$value === "tanh" ||
					$value === "sinh" ||
					$value === "cosh" ||
					$value === "deg" ||
					$value === "rad" ||
					$value === "exp" ||
					$value === "round" ||
					$value === "l" || $value === "s" ||
					$value === "lx" || $value === "ly" || $value === "sx" || $value === "sy" )
				{
					return true;
				} else {
					return false;
				}
			}

			function getToken ()
			{
				//	オーバーしてないですか？
				if ( $this->calc_source_len <= $this->token_position )
					{ return null; }

				$tmp = "";
				$dest = "";

				for ( ; $this->token_position < $this->calc_source_len; )
				{
					$tmp = $this->calc_source[$this->token_position++];
					$tmp2 = ( $this->token_position >= $this->calc_source_len ? "" : $this->calc_source[$this->token_position] );

					if ( ctype_alpha ( $tmp ) )
					{
						$dest .= $tmp;
						if ( !ctype_alpha ( $tmp2 ) )
						{
							return $dest;
						}
					} else {
						//	16進数を取得
						if ( $tmp === "0" && $tmp2 === "x" )
						{
							$xdigit = "";
							$this->token_position ++;
							for ( ;$this->token_position < $this->calc_source_len; $this->token_position ++ )
							{
								$tmp = $this->calc_source[$this->token_position];
								if ( !ctype_xdigit ( $tmp ) )
									{ break; }
								$xdigit .= $tmp;
							}
							return hexdec ( $xdigit );
						}

						//	2進数表現
						if ( $tmp === "0" && $tmp2 === "b" )
						{
							$bdigit = "";
							$this->token_position ++;
							for ( ;$this->token_position < $this->calc_source_len; $this->token_position ++ )
							{
								$tmp = $this->calc_source[$this->token_position];
								if ( !self::ctype_bdigit ( $tmp ) )
									{ break; }
								$bdigit .= $tmp;
							}
							return bindec ( $bdigit );
						}

						//	通常の数or浮動小数点を得る
						if ( is_numeric ( $tmp ) || $tmp === "." )
						{
							$dest .= $tmp;
							if ( !is_numeric ( $tmp2 ) && $tmp2 !== "." )
							{
								return $dest;
							}
						} else {
							return $tmp;
						}
					}
				}
				return $dest;
			}

			function ctype_bdigit ( $value )
			{
				return ( ( $value === "0" || $value === "1" )? true : false );
			}

			//push
			function push($val){
				array_unshift($this->arr,$val);
			}

			//pop
			function pop(){
				return array_shift($this->arr);
			}

			function initializeStack ()
			{
				$this->arr = array ();
			}

			function isEmptyStack ()
			{
				return empty ( $this->arr );
			}

			/**
				最後のスタックに追加した演算子と引数に指定された演算子を比較し、
				優先度が高ければtrueを返します

				引数: $token = 現在解析中の演算子
				返値: 優先度が高ければtrue、そうでなければfalseを返します
			**/
			private function isLowerPriority ( $token )
			{
				if ( $this->arr[0] === "*" || $this->arr[0] === "/" || $this->arr[0] === "%" || $this->arr[0] === "^" || $this->arr[0] === "&" ||
					self::isFunction ( $this->arr[0] ) )
				{
					return true;
				} else {
					if ( $this->arr[0] === "+" || $this->arr[0] === "-" )
					{
						if ( $token === "+" || $token === "-" )
						{
							return true;
						}
					}
				}
				return false;
			}

			function gcd($m, $n)
			{
				if($n == 0) return $m;
				return self::gcd($n, $m % $n);
			}

			function float2div ($float)
			{
				$base = $float;
				for ($i = 0; strpos((string) $base, '.') !== false; $i++) {
					$base *= 10;
				}

				if ($i === 0) {
					return $float;
				}

				$child = $base;
				$parent = pow(10, $i);

				//	ユークリッド互除法から最大公約数を求める
				$divisor = self::gcd($child, $parent);
				$child /= $divisor;
				$parent /= $divisor;

				return $child . '/' . $parent;
			}
		}

		//	テスト計算
		$calc = new CalcEval ();
		echo $calc->exec ( "(100+200)*10" );