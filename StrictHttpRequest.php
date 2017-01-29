<?php
/**
 * Возвращает типизированные входные данные запроса от клиента
 * Компонент Yii
 * @author v@kicha.pro <Кича Владимир>
 */
class StrictHttpRequest extends HttpRequest {
    
    const INPUT_TYPE_INT = 'integer';
	const INPUT_TYPE_STRING = 'string';
	const INPUT_TYPE_DOUBLE = 'double';
	const INPUT_TYPE_ARRAY = 'array';
	const INPUT_TYPE_BOOL = 'boolean';	
    
	/*
	* Функция получения, фильтрации и проверки переменной из $_GET-массива
	* @param string $varName - наименование переменной в $_GET-массиве
	* @param int StrictHttpRequest::INPUT_TYPE_* $type - требуемый тип переменной. Значение - константа из текущего класса
	* @param bool $required default false - Если true и переменной нет, выпадает exception
	* @param array $rules - значения для проверки: min/max - для чисел, length - для строк, default - по умол.
	* @return mixed
	*/
	final public function fromGet($varName, $type, $required = false, Array $rules = array()) {
		return $this->request($_GET, $varName, $type, $required, $rules);
	}
	
	/*
	* Функция получения, фильтрации и проверки переменной из $_POST-массива
	* @param string $varName - наименование переменной в $_POST-массиве
	* @param int StrictHttpRequest::INPUT_TYPE_* $type - требуемый тип переменной. Значение - константа из текущего класса
	* @param bool $required default false - Если true и переменной нет, выпадает exception
	* @param array $rules - значения для проверки: min/max - для чисел, length - для строк, default - по умол.
	* @return mixed
	*/
	final public function fromPost($varName, $type, $required = false, Array $rules = array()) {
		return $this->request($_POST, $varName, $type, $required, $rules);
	}
	
    
    /*
	* Функция получения, фильтрации и проверки переменной из JSON
	* @param array/string $varName - наименование переменной в JSON, 
    *           либо название объекта и свойства, которое нужно получить array('objectName' => 'propertyName')
	* @param int StrictHttpRequest::INPUT_TYPE_* $type - требуемый тип переменной. Значение - константа из текущего класса
	* @param bool $required default false - Если true и переменной нет, выпадает exception
	* @param array $rules - значения для проверки: min/max - для чисел, length - для строк, default - по умол.
	* @return mixed
	*/
    final public function fromJSON($varName, $type, $required = false, Array $rules = array()) {
        $json = CJSON::decode($this->getRawBody(), true);
        
        if (!$json)
            throw new Exception('Неверный запрос. Отсутствуют данные.'.htmlspecialchars($varName), 400);
        
        if (is_array($varName)) {
            //Достаем данные из свойства объекта
            $objectName = key($varName);
            
            if (!isset($json[$objectName])) {
                if ($required)
                    throw new Exception('Нет данных для запрашиваемого объекта '.htmlspecialchars($objectName), 400);
                else
                    return (isset($rules['default'])) ? $rules['default'] : null;
            }
            $object = $json[$objectName];
            
            return $this->request($object, $varName[$objectName], $type, $required, $rules);
        }
        else
            return $this->request($json, $varName, $type, $required, $rules);
        
    }
	/*
	* Функция получения, фильтрации и проверки переменной из массива
	* @access private
	* @param array $arrName - массив из которого необходимо получить значение
	* @param string $varName - наименование переменной в $_POST-массиве
	* @param int StrictHttpRequest::INPUT_TYPE_* $type - требуемый тип переменной. Значение - константа из текущего класса
	* @param bool $required default false - Если true и переменной нет, выпадает exception
	* @param array $rules - значения для проверки: min/max - для чисел, length - для строк, default - по умол.
	* @return mixed
	*/
	final private function request(Array $arrName, $varName, $type, $required = false, Array $rules = array()) {
		
		//Проверка на существование переменной
		if (!isset($arrName[$varName])) {
			
			//Если переменная обязательна, но её нет
			if ($required)
				throw new Exception('Неверный запрос. Отсутствует параметр "'.htmlspecialchars($varName).'".', 400);
			
			//Если переменная необязательна, но её нет, возвращаем значение по умолчанию, если оно есть
			if (isset($rules['default']))
				return $rules['default'];
				
			return false;
				
		}
		
		//trimming
		$var = ($type !== self::INPUT_TYPE_ARRAY) ? trim($arrName[$varName]) : $arrName[$varName];
		
		if($type == self::INPUT_TYPE_BOOL){ 
			$var = ($var === true || strtolower($var) === "true")? true : false;
		}
		
		//Жетское приведение типа для числа в строке
		if ($type == self::INPUT_TYPE_INT) {
			if (!(ctype_digit($var) || ctype_digit(ltrim($var, '-+'))))
		    	throw new Exception('Неверный запрос. Неверный тип параметра.'.htmlspecialchars($varName), 400);
				
			$var = (int)$var;
		}
			
		if ($type == self::INPUT_TYPE_DOUBLE)
			$var = (double)$var;
		
		//Проверяем тип переменной
		if (gettype($var) != $type)
			throw new Exception('Неверный запрос. Неверный тип параметра.'.htmlspecialchars($varName), 400);
			
		//Проверка на длину строкового параметра
		if ($type == self::INPUT_TYPE_STRING && isset($rules['length']) && mb_strlen($var) > $rules['length'])
			throw new Exception('Неверный запрос. Слишком длинный параметр.'.htmlspecialchars($varName), 400);
		
		//null-bytes remove
		if ($type == self::INPUT_TYPE_STRING)
			$var = str_replace(chr(0), "", $var);
		
		//Проверка на максимум-минимум числовых значений
		if (($type == self::INPUT_TYPE_INT || $type == self::INPUT_TYPE_DOUBLE) && 
			((isset($rules['min']) && $var < $rules['min'])
			|| (isset($rules['max']) && $var > $rules['max']))
			) {
				throw new Exception('Неверный запрос. Значение выходит из диапазона'.htmlspecialchars($varName), 400);
		}
		
		return $var;
	}
	
	
	/*
	* Проверяет массив и возвращает отфильтрованные значения в int, выдает exception, если одно из значения не Int.
	* @param array $array - массив с исходными значениями
	* @param bool $checkKeys default false - проверяет и приводит ключи к Int
	* @param int $min default min_php - минимально допустимое значение. По умолчанию минимально допустимое число
	* @param int $min default max_php - максимально допустимое значение. По умолчанию максимально допустимое число
	* @return array
	*/
	final public function filterArrayOfInt(Array $array, $checkKeys = false, $min = PHP_INT_MIN, $max = PHP_INT_MAX) {
		
		$newArray = array();
		
		foreach ($array as $key => $value) {
			
			if ($checkKeys) {
				if (!is_numeric($key))
					throw new Exception('Неверный запрос. Неверный тип параметра.', 400);
				
				$key = (int)$key;
				
				if ($key < $min || $key > $max)
					throw new Exception('Неверный запрос. Значение выходит из диапазона', 400);
			}
			
			if (!is_numeric($value))
					throw new Exception('Неверный запрос. Неверный тип параметра.', 400);
			
			if ($value < $min || $value > $max)
				throw new Exception('Неверный запрос. Значение выходит из диапазона', 400);
				
			$newArray[$key] = (int)$value;
		}
		
		return $newArray;
	}
    
    /*
	* Проверяет, является ли запрос пришедшим через AJAX
	* @return bool
	*/
	static public function IsAJAXQuery() {
		return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
	}
    
}

class SHR extends  StrictHttpRequest {} //alias