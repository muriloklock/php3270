<?php
/**
 * @package extensions\php3270
 */

//namespace extensions;



/**
 * Classe para maniputar terminais 3270.
 *
 * Classe que implementa em PHP a execução de comandos e
 * leitura de informações em telas de terminais 3270.
 * É implementado usando o programa s3270 (Unix/Linux), sendo
 * assim o mesmo é necessário para o uso desta classe.
 * @see http://x3270.bgp.nu/s3270-man.html http://x3270.bgp.nu/s3270-man.html
 *
 * @author Murilo Klock Ferreira <murilo.klock@gmail.com>
 *
 * @version 0.1
 */
class php3270 {

   /**
    * vetor com a definição dos ponteiros de leitura e escrita
    * @var array vetor com a definição dos ponteiros de leitura e escrita
    */
   var $descriptorspec = array(
      0 => array("pipe","r"),
      1 => array("pipe","w"),
      2 => array("pipe","w")
   );

   /**
    * vetor com os ponteiros de leitura e escrita
    * @var array vetor com os ponteiros de leitura e escrita
    */
   var $pipes=array();

   /**
    * Variável para guardar o resource obtido com a abertura do s3270
    * @var resource resource para o s3270
    */
   var $process="";

   /**
    * Variável para armazenar a tela com os dados
    * @var array armazena em um vetor os dados lidos na tela do terminal 3270
    */
   var $screen ="";

   /**
    * Variável para guardar o número de linhas do terminal 3270
    * @var int número de linhas do terminal 3270
    */
   var $lines="";

   /**
    * Variável para guardar o número de colunas do terminal 3270
    * @var int número de colunas do terminal 3270
    */
   var $columns="";

   /**
    * Variável para guardar o texto de erro do terminal 3270
    * @var string descrição do erro no terminal 3270
    */
   var $error="";

   /**
    * Variável para armazenar os dados de status do terminal 3270
    * @var array vetor os dados de status do terminal 3270
    */
   var $status_msg=array();

   /**
    * Variável para ligar ou desligar o debug
    * @var boolean liga ou desliga o debug
    */
   var $debug=false;

   /**
    * Variável para guardar o status da conexão, conectado ou desconectado.
    * @var boolean conectado ou desconectado
    */
   var $connected=true;

   /**
    * Variável para guardar o tempo de espera, quando necessário.
    * O padrão é 0, não esperastatus da conexão, conectado ou desconectado.
    * @var int tempo em microsegundos
    */
   var $waittime=0;

   /**
    * Construtor da classe. Abre um processo abilitando leitura e escrita ao mesmo.
    * Força os ponteiros para escrita e leitura para modo sem bloqueio.
    * Caso haja sucesso na abertura do processo, abre uma conexão com o servidor.
    * @see s3270::connect função para abrir conexão com servidor.
    *
    * @param string $hostname Nome do servidor ou endereço IP
    * @param int $port porta a ser usada para conextar
    *
    * @return boolean
    */
   function __construct($hostname, $port=23) {
      $this->process = proc_open('/usr/bin/s3270', $this->descriptorspec, $this->pipes, null, null);
      stream_set_blocking($this->pipes[0], 0);
      stream_set_blocking($this->pipes[1], 0);
      #$this->debug=true;

      if(is_resource($this->process)) {
         $this->connect($hostname, $port);
         return true;
      }
      else {
         $this->error="Erro de resource\n";
         echo $this->error;
         return false;
      }
   }


   /**
    * Envia comando Clear ao servidor
    *
    * @return void
    */
   final public function clear() {
      if($this->debug) echo "Função: ".__FUNCTION__."\n";

      $this->sendCommand("Clear()");
   }


   /**
    * Abre a conexão ao servidor dentro do processo s3270.
    *
    * @param string $hostname Nome do servidor ou endereço IP
    * @param int $port porta a ser usada para conextar
    *
    * @return boolean
    */
   private function connect($hostname, $port=23) {

      fwrite($this->pipes[0], "connect(".$hostname.":".$port.")\n");

      $this->waitUnlock();
      $this->waitInputField();
      $this->waitReturn();
      //fwrite($this->pipes[0], "enter()\n");
      //$this->waitReturn();
   }


   /**
    * Encerra conexão com servidor 3270
    *
    * @return void
    */
   function disconnect() {

      #$this->sendCommand('PF(10)');
      #$this->waitReturn();

      #$this->sendCommand('PF(12)');
      #$this->waitReturn();

      #@fwrite($this->pipes[0], "Disconnect()\n");
      $this->sendCommand("Disconnect()");

      $retorno="";
      $tela=array();


      //$this->waitReturn();
   }

   /**
    * Ascii(row,col,rows,cols)
    * ou
    * Ascii(row,col,length)
    * ou
    * Ascii(length)
    *
    * Exibe uma representação em texto ASCII da tela.
    * Cada linha é precedida pelo texto "data: ", e não existem caracteres de
    * controle.
    *
    * Se quatro argumentos são informados, uma região retangular
    * da tela é a saída.
    *
    * Se três argumentos são informados, $length caracteres são a saída,
    * começando pela linha e coluna especificada.
    *
    * Se apenas o argumento length é informado, este número de caracteres é
    * retornado como saída, començando pela posição atual do cursor.
    *
    * Se não são informados argumentos, a tela inteira é a saída.
    */
   function ascii() {
      if($this->debug) echo "Função: ".__FUNCTION__."\n";

      $argumentos = func_num_args();
      $lista      = func_get_args();

      if($argumentos > 4) die("Fatal, número de argumentos para ascii maior do que 4.");

      foreach($lista as $a=>$b) $lista[$a]=$b--;
      #die("Lista:#".implode(',',$lista)."#");
      $this->sendCommand("Ascii(".implode(',',$lista).")");
      #return $this->rectangle( implode(',',$lista) );
      #fwrite($this->pipes[0], "Ascii(".implode(',',$lista).")\n");
   }


   /**
    * Envia um comando ao terminal 3270
    *
    * @param string $cmd comando a ser enviado
    * @link http://x3270.bgp.nu/s3270-man.html#Actions http://x3270.bgp.nu/s3270-man.html#Actions
    *
    * @return boolean
    */
   final public function sendCommand($cmd) {
      if($this->debug) echo "Função: ".__FUNCTION__."\n";

      if(is_resource($this->process)) {
         if($this->debug) echo " escrevendo ".'fwrite($this->pipes[0], '.$cmd.')\n");'."<br />\n";
         fwrite($this->pipes[0], $cmd."\n");
         #fwrite($this->pipes[0], "ascii()\n");

         //$this->waitUnlock();

         #$this->esperaCampo();
         #$this->waitReturn();

      }
      else {
         $this->error="Erro de resource\n";
         echo $this->error;
         return false;
      }

      return true;
   }


   /**
    * Envia um texto ao terminal 3270
    *
    * @param string $texto comando a ser enviado
    * @param int [$row] opcional linha onde sera colocado o texto. A primeira linha é 1.
    * @param int [$col] opcional coluna onde sera colocado o texto. A primeira coluna é 1.
    * @link http://x3270.bgp.nu/s3270-man.html#Actions http://x3270.bgp.nu/s3270-man.html#Actions
    *
    * @return boolean
    */
   final public function sendString($string,$row=false,$col=false) {
      if($this->debug) echo "Função: ".__FUNCTION__."\n";

      if( $row!=false and $col!=false ) {
         $this->moveCursor($row,$col);
      }

      if(is_resource($this->process)) {
         if($this->debug) echo " escrevendo ".'fwrite($this->pipes[0], "string("'.$string.'")\n");'."<br />\n";
         fwrite($this->pipes[0], "string(".$string.")\n");
      }
      else {
         $this->error="Erro de resource\n";
         echo $this->error;
         return false;
      }

      return true;
   }


   /**
    * Aguarda que um campo de entrada de informações aparece no buffer de leitura
    *
    * @return boolean
    */
   final public function waitInputField() {
      if($this->debug) echo "Função: ".__FUNCTION__."\n";

      if(is_resource($this->process)) {
         fwrite($this->pipes[0], "Wait(InputField)\n");
      }
      else {
         $this->error="Erro de resource\n";
         echo $this->error;
         return false;
      }

      return true;
   }



   /**
    * Aguarda até que o teclado seja desbloqueado
    *
    * @return boolean
    */
   final public function waitUnlock() {
      if($this->debug) echo "Função: ".__FUNCTION__."\n";

      if(is_resource($this->process)) {
         fwrite($this->pipes[0], "Wait(Unlock)\n");
         #fwrite($this->pipes[0], "enter\n");
         #fwrite($this->pipes[0], "ascii()\n");
         #$this->readScreen();
      }
      else {
         $this->error="Erro de resource\n";
         echo $this->error;
         return false;
      }

      return true;
   }

   /**
    * Aguarda até que haja dados no buffer de leitura
    *
    * @return boolean
    */
   final public function waitOutput() {
      if($this->debug) echo "Função: ".__FUNCTION__."\n";

      if(is_resource($this->process)) {
         fwrite($this->pipes[0], "Wait(Output)\n");
         #fwrite($this->pipes[0], "enter\n");
         #fwrite($this->pipes[0], "ascii()\n");
         #$this->readScreen();
      }
      else {
         $this->error="Erro de resource\n";
         echo $this->error;
         return false;
      }

      return true;
   }


   /**
    * Espera até que o número de linhas de dados definidas na aplicação 3270
    * seja recebido
    *
    *
    * @param int $linhas número de linhas. Padrão 24 linhas
    *
    * @return boolean
    */
   final public function waitReturn($rows=false) {
      if($this->debug) echo "Função: ".__FUNCTION__."\n";
      $this->status_msg=array();

      if(is_resource($this->process)) {
         while(true) {

            $return = fgets($this->pipes[1]);
            if( $this->debug===true ) echo "\n\n".$return."\n\n";
            $re='/([U|L|E])\s([F|U])\s([P|U])\s(C\(.*\)|N)\s([I|L|C|P|N])\s([2-5])\s([0-9]+)\s([0-9]+)\s([0-9]+)\s([0-9]+)\s(0x[0-9]+)\s([0-9\.\-]+)/';

            if( preg_match($re,$return,$tmp) ) {
               if( $this->debug===true ) echo "\n\n".$return."\n\n";
               if( isset($tmp[1]) ) $this->status_msg['keyboard_state']   =$tmp[1];
               if( isset($tmp[2]) ) $this->status_msg['screen_formatting']=$tmp[2];
               if( isset($tmp[3]) ) $this->status_msg['field_protection'] =$tmp[3];
               if( isset($tmp[4]) ) $this->status_msg['connection_state'] =$tmp[4];
               if( isset($tmp[5]) ) $this->status_msg['emulator_mode']    =$tmp[5];
               if( isset($tmp[6]) ) $this->status_msg['model_number']     =$tmp[6];
               if( isset($tmp[7]) ) $this->status_msg['number_of_rows']   =$tmp[7];
               if( isset($tmp[8]) ) $this->status_msg['number_of_cols']   =$tmp[8];
               if( isset($tmp[9]) ) $this->status_msg['cursor_row']       =$tmp[9];
               if( isset($tmp[10]) ) $this->status_msg['cursor_col']      =$tmp[10];
               if( isset($tmp[11]) ) $this->status_msg['window_id']       =$tmp[11];
               if( isset($tmp[12]) ) $this->status_msg['command_exec_time']=$tmp[12];
            }
            #if( $this->debug===true ) print_r( $this->status_msg);
            if(!$rows and !empty($this->status_msg['number_of_rows'])) $rows=$this->status_msg['number_of_rows'];
            elseif(!$rows) $rows=24;
            if( isset($this->status_msg['keyboard_state'])
                and $this->status_msg['keyboard_state']=='E' ) $this->clear();

            if( preg_match( '/ok/',$return) ) {
               #echo "\n\n".$return."\n\n";
               #if( $this->debug===true ) print_r( $this->status_msg);
               $this->readScreen($rows);
               return true;
            }
            elseif( preg_match( '/error/',$return) ) {
               $this->readScreen($rows);
               $this->error="Erro ao conectar ao servidor MainFrame\n";
               $this->connected=false;
               return false;
            }
            elseif( preg_match( '/locked/is',$return) ) {
               $this->reset();
            }
            elseif( preg_match( '/limpe/is',$return) ) {
               $this->clear();
            }
            elseif( preg_match( '/wait/is',$return) ) {
               $this->waitUnlock();
            }
         }
      }
      else {
         $this->error="Erro de resource\n";
         echo $this->error;
         return false;
      }
   }


   /**
    * Envia uma ação denominada Program Function (função de programa)
    * que pode se um número entre 1 e 24 (PF(1) até PF(24))
    *
    *
    * @param int $n número da ação (entre 1 e 24).
    *
    * @return boolean
    */
   final public function pf($n) {
      if($this->debug) echo "Função: ".__FUNCTION__."\n";

      if( !is_numeric($n) and ($n<1 or $n>24) ) {
         $this->error="Função de programa deve ser um número entre 1 e 24.";
         return false;
      }

      $this->sendCommand("PF(".$n.")");
   }



   /**
    * Lê o número definido de linhas de dados definidas na aplicação 3270
    *
    *
    * @param int $linhas número de linhas. Padrão 24 linhas
    *
    * @return boolean
    */
   final public function readScreen($rows=false) {
      if($this->debug) echo "Função: ".__FUNCTION__."\n";

      $this->screen =  array();
      if(!$rows) $rows=$this->status_msg['number_of_rows'];
      $inicio = false;
      $retorno = "";

      if(is_resource($this->process)) {
         #fwrite($this->pipes[0], "Wait(Output)\n");
         #fwrite($this->pipes[0], "ascii()\n");
         $this->waitOutput();
         $this->ascii();
         if( $this->debug===true ) {
            echo 'Número de linhas em $this->screen: '.count($this->screen)."\n";
            echo 'Número de linhas em $rows: '.$rows."\n";
         }
         while ( count($this->screen) < $rows) {
            $retorno = fgets($this->pipes[1]);

            if( preg_match( '/locked/is',$retorno) ) {
               $this->reset();
            }
            elseif( preg_match( '/limpe/is',$retorno) ) {
               $this->clear();
            }
            elseif( preg_match( '/wait/is',$retorno) ) {
               $this->waitUnlock();
            }

            #if( $this->debug===true ) {
            #   echo "Retorno: ".$retorno."\n";
            #}
            #if( count($this->screen)>1 and preg_match('/^data:(.*)/i',$retorno,$tmp) and !$inicio ) {
            #   if( trim($tmp[1]) == '' ) continue;
            #}
            #else $inicio=false;

            if( !empty($retorno) and preg_match('/^data/',$retorno) ) $this->screen[] = $retorno;
            #fwrite($pipes[0], "ascii()\n");
            usleep($this->waittime);
         }
         if( $this->debug===true ) {
            echo "Vamos imprimir o Vetor com o retorno\n";
            print_r($this->screen);
         }
         return true;
      }
      else {
         $this->error="Erro de resource\n";
         echo $this->error;
         return false;
      }
   }

   /**
    * rectangle(row,col,rows,cols)
    * ou
    * rectangle(row,col,length)
    * ou
    * rectangle(length)
    *
    * Exibe uma representação em texto ASCII da tela.
    * Cada linha é precedida pelo texto "data: ", e não existem caracteres de
    * controle.
    *
    * Se quatro argumentos são informados, uma região retangular
    * da tela é a saída.
    *
    * Se três argumentos são informados, length caracteres são a saída,
    * começando pela linha e coluna especificada.
    *
    * Se apenas o argumento length é informado, este número de caracteres é
    * retornado como saída, començando pela posição atual do cursor.
    *
    * Se não são informados argumentos, a tela inteira é a saída.
    */
   final public function rectangle() {
      if($this->debug) echo "Função: ".__FUNCTION__."\n";

      $argumentos = func_num_args();
      $lista      = func_get_args();
      $saida      = '';

      if($argumentos > 4) die("Fatal, número de argumentos para ascii maior do que 4.");

      if( $argumentos== 4 ) {
         $lista[0]=($lista[0]-1);
         $lista[2]=($lista[2]-1);
      }
      if( $argumentos== 3 ) $lista[0]=$lista[0]-1;

      $tmp = $this->screen;
      foreach($tmp as $a => $b) $tmp[$a]=preg_replace('/^data:/','',$b);


      if($argumentos==4) {
         foreach($tmp as $a=>$b) {
            if($a<$lista[0] or $a>$lista[2]) continue;
            $saida.=substr($b,$lista[1],($lista[3]-$lista[1]))."\n";
         }
      }

      if($argumentos==3) {
         $saida=substr($tmp[$lista[0]],$lista[1],$lista[2]);
      }
      elseif($argumentos==1 and !empty($lista[0])) {
         $saida=substr($b,0,$lista[0]);
      }
      elseif($argumentos==0) {
            $saida=implode("\n",$tmp);
      }
      return $saida;
   }


   /**
    * Envia comando Reset ao servidor
    *
    * @return void
    */
   final public function reset() {
      if($this->debug) echo "Função: ".__FUNCTION__."\n";

      $this->sendCommand("Reset()");
   }

   /**
    * Envia comando EraseInput ao servidor. Apaga todos valores em campos
    * de entrada de dados
    *
    * @return void
    */
   final public function eraseInput() {
      if($this->debug) echo "Função: ".__FUNCTION__."\n";

      $this->sendCommand("EraseInput()");
   }

   /**
    * Movo cursor para a posição determinada pela linha e pela coluna informados
    * @param int $row
    * @param int $col
    * @return boolean
    */
   final public function moveCursor($row,$col) {
      if($this->debug) echo "Função: ".__FUNCTION__."\n";

      if( $row!=false and $col!=false ) {
         $row--;
         $col--;
      }
      $this->sendCommand("MoveCursor(".$row.", ".$col.")");
      #fwrite($this->pipes[0], "MoveCursor(".$row.", ".$col.")\n");
      #$this->waitUnlock();
      return true;
   }



   /**
    * Converte os dados lidos do formato Array para String
    *
    *
    * @return string $strTela Dados da tela do terminal 3270 em formato texto.
    */
   final public function screenToString() {
      if($this->debug) echo "Função: ".__FUNCTION__."\n";

      $strTela='';
      if( isset($this->screen) and is_array($this->screen) ) {
         $tmp = $this->screen;
         foreach($tmp as $a => $b) $tmp[$a]=preg_replace('/^data:/','',$b);
         $strTela = implode( "", $tmp );
      }
      return $strTela;
   }


   /**
    * Função para aguardar que determinado texto apareça na tela (buffer)
    *
    * @param string string a ser esperado
    *
    * @return string $screen texto da tela
    */
   final public function waitString ($string) {
      if($this->debug) echo "Função: ".__FUNCTION__."\n";

      $x = false;
      $g = false;
      $tela = "";
      $linha=1;


      while ($x == false) {
         usleep(1000);
         while (false !== ($mydat = fgets($this->pipes[1]))) {
            echo $mydat."\n";
            $tela.=$mydat;
            if (preg_match("/".$string."/i", $mydat)) {
               $g = true;
            }
            if ($g == true) {

               while (false !== ($mydat = fgets($this->pipes[1]))) {

                  $tela.=$mydat;
                  #echo $mydat."\n";
               }

               $x = true;
               break 2;
            }
         }
      #fwrite($pipes[0], "ascii()\n");
      }
      return $tela;
   }



   /**
    * Destrutor da classe. Fecha os ponteiros de leitura e escrita abertos
    * e fecha o processo.
    */
   function __destruct() {

      foreach ($this->pipes as $pipe) {
         @fclose($pipe);
      }

      @proc_close($this->process);
   }


}

?>
