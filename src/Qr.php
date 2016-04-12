<?php
/**
 *  @author Rob Thomassen (rob@shitware.nl)
 *  @copyright Copyright © 2011-2015 Royal Shitware Inc. (www.shitware.nl)
 *  @version 0.1
 *
 *  This program is free software: you can redistribute it and/or modify it under the terms of the GNU Lesser General Public
 *  License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 *  version.
 *
 *  This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
 *  of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public License for more details.
 *
 *  You should have received a copy of the GNU Lesser General Public License along with this program. If not, see
 *  http://www.gnu.org/licenses/.
 */

namespace Rsi;

/**
 *  QR-code generator.
 *  According to ISO/IEC 18004 (without Kanji mode and mode change).
 *  <a href="http://www.swetake.com/qr/qr1_en.html">How to create QRcode</a>
 */
class Qr{

  const MODE_NUM = '0001';
  const MODE_ALPHA = '0010';
  const MODE_DATA = '0100';

  const ECC_LOW = '01';
  const ECC_MEDIUM = '00';
  const ECC_QUARTILE = '11';
  const ECC_HIGH = '10';

  public $ecc;
  public $version;
  public $resolution;

  /**
   *  Creates a Rsi\\Qr object.
   *  @param string $ecc  The error correction level.
   *  @param int $version  The version for the QR code (1..40). If empty the smallest size/lowest version possible will be selected.
   *  @param int $resolution  Width of a bit (dot) in pixels.
   *  @return Rsi\\Qr
   */
  public function __construct($ecc = self::ECC_HIGH,$version = null,$resolution = 1){
    $this->ecc = $ecc;
    $this->version = $version;
    $this->resolution = $resolution;
  }
  /**
   *  The applicable mode.
   *  @param string $data  The data to encode.
   *  @return string  The (minimal) applicable mode (4-bit, see MODE_* constants).
   */
  public function determineMode($data){
    if(preg_match('/^\d*$/',$data)) return self::MODE_NUM;
    if(preg_match('/^[\dA-Z \$\%\*\+\-\.\/\:]*$/',$data)) return self::MODE_ALPHA;
    return self::MODE_DATA;
  }
  /**
   *  Length for the data length field.
   *  @param int $version  QR version (1..40).
   *  @param string $mode  QR mode.
   *  @return int  Length of the length field, in bits.
   */
  public function lenLen($version,$mode){
    if($version <= 9) $len = [self::MODE_NUM => 10,self::MODE_ALPHA => 9,self::MODE_DATA => 8];
    elseif($version <= 26) $len = [self::MODE_NUM => 12,self::MODE_ALPHA => 11,self::MODE_DATA => 16];
    else $len = [self::MODE_NUM => 14,self::MODE_ALPHA => 13,self::MODE_DATA => 16];
    return $len[$mode];
  }
  /**
   *  Dot size for a certain version.
   *  @param int $version  QR version (1..40).
   *  @return int  Size in bits/dots.
   */
  public function versionSize($version){
    return 17 + $version * 4;
  }
  /**
   *  Number of alignment patterns at one side.
   *  Not taking into account the 'missing' patterns at the finder patterns.
   *  @param int $version  QR version (1..40).
   *  @return int  Number of alignment patterns (0 for version 1).
   */
  public function alignmentCount($version){
    if($version == 1) return 0;
    return ceil(($version + 8) / 7);
  }
  /**
   *  Positions of alignment patterns.
   *  @param int $version  QR version (1..40).
   *  @return array  Positions of alignment patterns.
   */
  public function alignmentPositions($version){
    $positions = [];
    if($version > 1){
      $size = $this->versionSize($version);
      $count = $this->alignmentCount($version);
      $delta = ceil(($size - 14) / ($count - 1));
      if($delta & 1) $delta++; //round up to nearest even number
      $i = $size - 7; //last
      while(--$count){
        array_unshift($positions,$i);
        $i -= $delta;
      }
      array_unshift($positions,6); //first
    }
    return $positions;
  }
  /**
   *  Total byte capacity (data + ECC).
   *  @param int $version  QR version (1..40).
   *  @return int  Capacity in bytes.
   */
  public function versionCapacity($version){
    $fixed = 225; //9*9 + 8*9 + 9*8: finder + separators
    if($version >= 7) $fixed += 36; //2 * 3*6: version information
    $fixed += $version * 8; //timing pattern (2x)
    if($align = $this->alignmentCount($version)) $fixed += pow($align - 1,2) * 25 + ($align - 2) * 40; //alignment pattern
    return floor((pow($this->versionSize($version),2) - $fixed) / 8); //bit->byte
  }
  /**
   *  Byte capacity for data (excluding ECC).
   *  @param int $version  QR version (1..40).
   *  @param string $ecc  The error correction level.
   *  @return int  Capacity in bytes.
   */
  public function versionDataCapacity($version,$ecc){
    $capacity = [ //version        1  2  3  4   5   6   7   8   9  10  11  12  13  14  15  16  17  18  19  20  21   22   23   24   25   26   27   28   29   30   31   32   33   34   35   36   37   38   39   40
      self::ECC_LOW      => [null,19,34,55,80,108,136,156,194,232,274,324,370,428,461,523,589,647,721,795,861,932,1006,1094,1174,1276,1370,1468,1531,1631,1735,1843,1955,2071,2191,2306,2434,2566,2702,2812,2956],
      self::ECC_MEDIUM   => [null,16,28,44,64, 86,108,124,154,182,216,254,290,334,365,415,453,507,563,627,669,714, 782, 860, 914,1000,1062,1128,1193,1267,1373,1455,1541,1631,1725,1812,1914,1992,2102,2216,2334],
      self::ECC_QUARTILE => [null,13,22,34,48, 62, 76, 88,110,132,154,180,206,244,261,295,325,367,397,445,485,512, 568, 614, 664, 718, 754, 808, 871, 911, 985,1033,1115,1171,1231,1286,1354,1426,1502,1582,1666],
      self::ECC_HIGH     => [null, 9,16,26,36, 46, 60, 66, 86,100,122,140,158,180,197,223,253,283,313,341,385,406, 442, 464, 514, 538, 596, 628, 661, 701, 745, 793, 845, 901, 961, 986,1054,1096,1142,1222,1276]
    ];
    return $capacity[$ecc][$version];
  }
  /**
   *  Number of blocks and dimensions.
   *  @param int $version  QR version (1..40).
   *  @param string $ecc  The error correction level.
   *  @return array  One item for each block with the following items: c (number of bytes total), k (number of data bytes), r (error correction capacity).
   */
  public function versionBlockInfo($version,$ecc){
    $count = [ //version          1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20 21 22 23 24 25 26 27 28 29 30 31 32 33 34 35 36 37 38 39 40
      self::ECC_LOW      => [null,1,1,1,1,1,2,2,2,2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9,10,12,12,12,13,14,15,16,17,18,19,19,20,21,22,24,25],
      self::ECC_MEDIUM   => [null,1,1,1,2,2,4,4,4,5, 5, 5, 8, 9, 9,10,10,11,13,14,16,17,17,18,20,21,23,25,26,28,29,31,33,35,37,38,40,43,45,47,49],
      self::ECC_QUARTILE => [null,1,1,2,2,4,4,6,6,8, 8, 8,10,12,16,12,17,16,18,21,20,23,23,25,27,29,34,34,35,38,40,43,45,48,51,53,56,59,62,65,68],
      self::ECC_HIGH     => [null,1,1,2,4,4,4,5,6,8, 8,11,11,16,16,18,16,19,21,25,25,25,34,30,32,35,37,40,42,45,48,51,54,57,60,63,66,70,74,77,81]
    ];
    $count = $count[$ecc][$version];

    $capacity = $this->versionDataCapacity($version,$ecc);
    $size = $capacity / $count;
    if($size == round($size)) $blocks = array_fill(0,$count,['k' => $size]);
    else{
      $size1 = floor($size);
      $size2 = ceil($size);
      for($n = 0; $n < $count; $n++) if($n * $size1 + ($count - $n) * $size2 == $capacity){
        $blocks = array_merge(
          array_fill(0,$n,['k' => $size1]),
          array_fill(0,$count - $n,['k' => $size2])
        );
        break;
      }
    }

    switch($version . $ecc){
      case 1 . self::ECC_LOW: $p = 3; break;
      case 1 . self::ECC_MEDIUM:
      case 2 . self::ECC_LOW: $p = 2; break;
      case 1 . self::ECC_QUARTILE:
      case 1 . self::ECC_HIGH:
      case 3 . self::ECC_LOW: $p = 1; break;
      default: $p = 0;
    }
    $cor_cap = ($this->versionCapacity($version) - $capacity - $p) / $count / 2;

    foreach($blocks as &$block){
      $block['r'] = $cor_cap;
      $block['c'] = $block['k'] + 2 * $cor_cap + $p;
    }
    unset($block);
    return $blocks;
  }
  /**
   *  GF(2^8) function.
   *  @param int $arg  Input value
   *  @param bool $reverse  Normally $arg is used as the exponent. If this value is true the exponent will be returned.
   *  @return int  The (reverse) result of the GF function.
   */
  public function gf($arg,$reverse = false){
    $gf = [
        0 =>   1,  1 =>   2,  2 =>   4,  3 =>   8,  4 =>  16,  5 =>  32,  6 =>  64,  7 => 128,  8 =>  29,  9 =>  58, 10 => 116, 11 => 232, 12 => 205, 13 => 135, 14 =>  19, 15 =>  38,
       16 =>  76, 17 => 152, 18 =>  45, 19 =>  90, 20 => 180, 21 => 117, 22 => 234, 23 => 201, 24 => 143, 25 =>   3, 26 =>   6, 27 =>  12, 28 =>  24, 29 =>  48, 30 =>  96, 31 => 192,
       32 => 157, 33 =>  39, 34 =>  78, 35 => 156, 36 =>  37, 37 =>  74, 38 => 148, 39 =>  53, 40 => 106, 41 => 212, 42 => 181, 43 => 119, 44 => 238, 45 => 193, 46 => 159, 47 =>  35,
       48 =>  70, 49 => 140, 50 =>   5, 51 =>  10, 52 =>  20, 53 =>  40, 54 =>  80, 55 => 160, 56 =>  93, 57 => 186, 58 => 105, 59 => 210, 60 => 185, 61 => 111, 62 => 222, 63 => 161,
       64 =>  95, 65 => 190, 66 =>  97, 67 => 194, 68 => 153, 69 =>  47, 70 =>  94, 71 => 188, 72 => 101, 73 => 202, 74 => 137, 75 =>  15, 76 =>  30, 77 =>  60, 78 => 120, 79 => 240,
       80 => 253, 81 => 231, 82 => 211, 83 => 187, 84 => 107, 85 => 214, 86 => 177, 87 => 127, 88 => 254, 89 => 225, 90 => 223, 91 => 163, 92 =>  91, 93 => 182, 94 => 113, 95 => 226,
       96 => 217, 97 => 175, 98 =>  67, 99 => 134,100 =>  17,101 =>  34,102 =>  68,103 => 136,104 =>  13,105 =>  26,106 =>  52,107 => 104,108 => 208,109 => 189,110 => 103,111 => 206,
      112 => 129,113 =>  31,114 =>  62,115 => 124,116 => 248,117 => 237,118 => 199,119 => 147,120 =>  59,121 => 118,122 => 236,123 => 197,124 => 151,125 =>  51,126 => 102,127 => 204,
      128 => 133,129 =>  23,130 =>  46,131 =>  92,132 => 184,133 => 109,134 => 218,135 => 169,136 =>  79,137 => 158,138 =>  33,139 =>  66,140 => 132,141 =>  21,142 =>  42,143 =>  84,
      144 => 168,145 =>  77,146 => 154,147 =>  41,148 =>  82,149 => 164,150 =>  85,151 => 170,152 =>  73,153 => 146,154 =>  57,155 => 114,156 => 228,157 => 213,158 => 183,159 => 115,
      160 => 230,161 => 209,162 => 191,163 =>  99,164 => 198,165 => 145,166 =>  63,167 => 126,168 => 252,169 => 229,170 => 215,171 => 179,172 => 123,173 => 246,174 => 241,175 => 255,
      176 => 227,177 => 219,178 => 171,179 =>  75,180 => 150,181 =>  49,182 =>  98,183 => 196,184 => 149,185 =>  55,186 => 110,187 => 220,188 => 165,189 =>  87,190 => 174,191 =>  65,
      192 => 130,193 =>  25,194 =>  50,195 => 100,196 => 200,197 => 141,198 =>   7,199 =>  14,200 =>  28,201 =>  56,202 => 112,203 => 224,204 => 221,205 => 167,206 =>  83,207 => 166,
      208 =>  81,209 => 162,210 =>  89,211 => 178,212 => 121,213 => 242,214 => 249,215 => 239,216 => 195,217 => 155,218 =>  43,219 =>  86,220 => 172,221 =>  69,222 => 138,223 =>   9,
      224 =>  18,225 =>  36,226 =>  72,227 => 144,228 =>  61,229 => 122,230 => 244,231 => 245,232 => 247,233 => 243,234 => 251,235 => 235,236 => 203,237 => 139,238 =>  11,239 =>  22,
      240 =>  44,241 =>  88,242 => 176,243 => 125,244 => 250,245 => 233,246 => 207,247 => 131,248 =>  27,249 =>  54,250 => 108,251 => 216,252 => 173,253 =>  71,254 => 142,255 =>   1
    ];
    return $reverse ? array_search($arg,$gf) : $gf[$arg % 255];
  }
  /**
   *  ECC polynomials information.
   *  @param int $size  Number of ECC bytes.
   *  @return array  Alfa coefficients for the polynomial.
   */
  public function eccCoefs($size){
    $coefs = [
       7 => [ 87,229,146,149,238,102, 21],
      10 => [251, 67, 46, 61,118, 70, 64, 94, 32, 45],
      13 => [ 74,152,176,100, 86,100,106,104,130,218,206,140, 78],
      15 => [  8,183, 61, 91,202, 37, 51, 58, 58,237,140,124,  5, 99,105],
      16 => [120,104,107,109,102,161, 76,  3, 91,191,147,169,182,194,225,120],
      17 => [ 43,139,206, 78, 43,239,123,206,214,147, 24, 99,150, 39,243,163,136],
      18 => [215,234,158, 94,184, 97,118,170, 79,187,152,148,252,179,  5, 98, 96,153],
      20 => [ 17, 60, 79, 50, 61,163, 26,187,202,180,221,225, 83,239,156,164,212,212,188,190],
      22 => [210,171,247,242, 93,230, 14,109,221, 53,200, 74,  8,172, 98, 80,219,134,160,105,165,231],
      24 => [229,121,135, 48,211,117,251,126,159,180,169,152,192,226,228,218,111,  0,117,232, 87, 96,227, 21],
      26 => [173,125,158,  2,103,182,118, 17,145,201,111, 28,165, 53,161, 21,245,142, 13,102, 48,227,153,145,218, 70],
      28 => [168,223,200,104,224,234,108,180,110,190,195,147,205, 27,232,201, 21, 43,245, 87, 42,195,212,119,242, 37,  9,123],
      30 => [ 41,173,145,152,216, 31,179,182, 50, 48,110, 86,239, 96,222,125, 42,173,226,193,224,130,156, 37,251,216,238, 40,192,180],
      32 => [ 10,  6,106,190,249,167,  4, 67,209,138,138, 32,242,123, 89, 27,120,185, 80,156, 38, 69,171, 60, 28,222, 80, 52,254,185,220,241],
      34 => [111, 77,146, 94, 26, 21,108, 19,105, 94,113,193, 86,140,163,125, 58,158,229,239,218,103, 56, 70,114, 61,183,129,167, 13, 98, 62,129, 51],
      36 => [200,183, 98, 16,172, 31,246,234, 60,152,115,  0,167,152,113,248,238,107, 18, 63,218, 37, 87,210,105,177,120, 74,121,196,117,251,113,233, 30,120],
      40 => [ 59,116, 79,161,252, 98,128,205,128,161,247, 57,163, 56,235,106, 53, 26,187,174,226,104,170,  7,175, 35,181,114, 88, 41, 47,163,125,134, 72, 20,232, 53, 35, 15],
      42 => [250,103,221,230, 25, 18,137,231,  0,  3, 58,242,221,191,110, 84,230,  8,188,106, 96,147, 15,131,139, 34,101,223, 39,101,213,199,237,254,201,123,171,162,194,117, 50, 96],
      44 => [190,  7, 61,121, 71,246, 69, 55,168,188, 89,243,191, 25, 72,123,  9,145, 14,247,  1,238, 44, 78,143, 62,224,126,118,114, 68,163, 52,194,217,147,204,169, 37,130,113,102, 73,181],
      46 => [112, 94, 88,112,253,224,202,115,187, 99, 89,  5, 54,113,129, 44, 58, 16,135,216,169,211, 36,  1,  4, 96, 60,241, 73,104,234,  8,249,245,119,174, 52, 25,157,224, 43,202,223, 19, 82, 15],
      48 => [228, 25,196,130,211,146, 60, 24,251, 90, 39,102,240, 61,178, 63, 46,123,115, 18,221,111,135,160,182,205,107,206, 95,150,120,184, 91, 21,247,156,140,238,191, 11, 94,227, 84, 50,163, 39, 34,108],
      50 => [232,125,157,161,164,  9,118, 46,209, 99,203,193, 35,  3,209,111,195,242,203,225, 46, 13, 32,160,126,209,130,160,242,215,242, 75, 77, 42,189, 32,113, 65,124, 69,228,114,235,175,124,170,215,232,133,205],
      52 => [116, 50, 86,186, 50,220,251, 89,192, 46, 86,127,124, 19,184,233,151,215, 22, 14, 59,145, 37,242,203,134,254, 89,190, 94, 59, 65,124,113,100,233,235,121, 22, 76, 86, 97, 39,242,200,220,101, 33,239,254,116, 51],
      54 => [183, 26,201, 87,210,221,113, 21, 46, 65, 45, 50,238,184,249,225,102, 58,209,218,109,165, 26, 95,184,192, 52,245, 35,254,238,175,172, 79,123, 25,122, 43,120,108,215, 80,128,201,235,  8,153, 59,101, 31,198, 76, 31,156],
      56 => [106,120,107,157,164,216,112,116,  2, 91,248,163, 36,201,202,229,  6,144,254,155,135,208,170,209, 12,139,127,142,182,249,177,174,190, 28, 10, 85,239,184,101,124,152,206, 96, 23,163, 61, 27,196,247,151,154,202,207, 20, 61,10],
      58 => [ 82,116, 26,247, 66, 27, 62,107,252,182,200,185,235, 55,251,242,210,144,154,237,176,141,192,248,152,249,206, 85,253,142, 65,165,125, 23, 24, 30,122,240,214,  6,129,218, 29,145,127,134,206,245,117, 29, 41, 63,159,142,233,125,148,123],
      60 => [107,140, 26, 12,  9,141,243,197,226,197,219, 45,211,101,219,120, 28,181,127,  6,100,247,  2,205,198, 57,115,219,101,109,160, 82, 37, 38,238, 49,160,209,121, 86, 11,124, 30,181, 84, 25,194, 87, 65,102,190,220, 70, 27,209, 16, 89,  7, 33,240],
      62 => [ 65,202,113, 98, 71,223,248,118,214, 94,  0,122, 37, 23,  2,228, 58,121,  7,105,135, 78,243,118, 70, 76,223, 89, 72, 50, 70,111,194, 17,212,126,181, 35,221,117,235, 11,229,149,147,123,213, 40,115,  6,200,100, 26,246,182,218,127,215, 36,186,110,106],
      64 => [ 45, 51,175,  9,  7,158,159, 49, 68,119, 92,123,177,204,187,254,200, 78,141,149,119, 26,127, 53,160, 93,199,212, 29, 24,145,156,208,150,218,209,  4,216, 91, 47,184,146, 47,140,195,195,125,242,238, 63, 99,108,140,230,242, 31,204, 11,178,243,217,156,213,231],
      66 => [  5,118,222,180,136,136,162, 51, 46,117, 13,215, 81, 17,139,247,197,171, 95,173, 65,137,178, 68,111, 95,101, 41, 72,214,169,197, 95,  7, 44,154, 77,111,236, 40,121,143, 63, 87, 80,253,240,126,217, 77, 34,232,106, 50,168, 82, 76,146, 67,106,171, 25,132, 93, 45,105],
      68 => [247,159,223, 33,224, 93, 77, 70, 90,160, 32,254, 43,150, 84,101,190,205,133, 52, 60,202,165,220,203,151, 93, 84, 15, 84,253,173,160, 89,227, 52,199, 97, 95,231, 52,177, 41,125,137,241,166,225,118,  2, 54, 32, 82,215,175,198, 43,238,235, 27,101,184,127,  3,  5,  8,163,238]
    ];
    return $coefs[$size];
  }
  /**
   *  Binary string.
   *  @param int $int  Decimal value.
   *  @param int $len  Length of the binary string (nnumber of bits).
   *  @return string  The binary string (zeros and ones) voor $int.
   */
  public function bin($int,$len){
    return str_pad(decbin($int),$len,'0',STR_PAD_LEFT);
  }
  /**
   *  Encode data.
   *  @param string $data  The data to encode.
   *  @param string $mode  QR mode to use.
   *  @return string  The encoded data.
   */
  public function encodeData($data,$mode){
    $len = strlen($data);
    $encoded = '';
    switch($mode){
      case self::MODE_NUM:
        $i = 0;
        while($i <= $len - 3){
          $encoded .= $this->bin((int)substr($data,$i,3),10);
          $i += 3;
        }
        if($i < $len) $encoded .= $this->bin((int)substr($data,$i),$len - $i == 1 ? 4 : 7);
        break;
      case self::MODE_ALPHA:
        $table = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';
        $i = 0;
        while($i <= $len - 2){
          $encoded .= ($x = $this->bin(strpos($table,$data[$i]) * 45 + strpos($table,$data[$i + 1]),11));
          $i += 2;
        }
        if($i < $len) $encoded .= $this->bin(strpos($table,$data[$i]),6);
        break;
      case self::MODE_DATA:
        for($i = 0; $i < $len; $i++) $encoded .= $this->bin(ord($data[$i]),8);
        break;
    }
    return $encoded;
  }
  /**
   *  Adds an alignment pattern.
   *  @param array $array  Array to add the pattern to.
   *  @param int $x  Center x-coordinate.
   *  @param int $y  Center y-coordinate.
   *  @param int $finder  Set to true to add a finder pattern (3 bit/dot center instead of 1).
   */
  public function addAlignment(&$array,$x,$y,$finder = false){
    //black center
    $offset = (int)$finder;
    for($i = -$offset; $i <= $offset; $i++) for($j = -$offset; $j <= $offset; $j++) $array[$x + $i][$y + $j] = 3;
    //white rectangle
    $offset++;
    for($i = -$offset; $i <= $offset; $i++){
      $array[$x + $i][$y - $offset] = 2;
      $array[$x + $i][$y + $offset] = 2;
      $array[$x - $offset][$y + $i] = 2;
      $array[$x + $offset][$y + $i] = 2;
    }
    //black border
    $offset++;
    for($i = -$offset; $i <= $offset; $i++){
      $array[$x + $i][$y - $offset] = 3;
      $array[$x + $i][$y + $offset] = 3;
      $array[$x - $offset][$y + $i] = 3;
      $array[$x + $offset][$y + $i] = 3;
    }
  }
  /**
   *  Add a bit/dot mirror symmetric.
   *  De bit/dot will be placed at i,j and at j,i
   *  @param array $array  Array to add the bit/dot to.
   *  @param int $i  The x/y-coordinate.
   *  @param int $j  The y/x-coordinate.
   *  @param int $color  Color (0-3).
   */
  public function addSymetric(&$array,$i,$j,$color){
    $array[$i][$j] = $color;
    $array[$j][$i] = $color;
  }
  /**
   *  Creates a basic array.
   *  Two dimensional array where each item matches a bit/dot. The reserved areas will be drawn with colors 2/3 (white/black).
   *  Free space has a null value.
   *  @param int $version  QR version (1..40).
   *  @return array  QR code array.
   */
  public function createArray($version){
    $array = [0 => array_fill(0,$size = $this->versionSize($version),null)];
    for($i = 1; $i < $size; $i++) $array[$i] = $array[0];

    //finder patterns
    $this->addAlignment($array,3,3,true);
    $this->addAlignment($array,$size - 4,3,true);
    $this->addAlignment($array,3,$size - 4,true);

    for($i = 0; $i < 8; $i++){
      //white borders around alignments
      $this->addSymetric($array,7,$i,2);
      $this->addSymetric($array,$size - 8,$i,2);
      $this->addSymetric($array,$size - 1 - $i,7,2);
      //reserved space for format information
      $this->addSymetric($array,$i,8,2);
      $this->addSymetric($array,$size - 1 - $i,8,2);
    }
    $array[8][8] = 2; //reserved space for format information
    $array[8][$size - 8] = 3; //black dot bottom left

    //timing pattern
    for($i = 8; $i < $size - 8; $i++) $this->addSymetric($array,$i,6,$i & 1 ? 2 : 3);

    //alignment patterns
    if($positions = $this->alignmentPositions($version)){
      $max = count($positions) - 1;
      foreach($positions as $i => $x) foreach($positions as $j => $y)
        if(($i || $j) && ($i || ($j != $max)) && ($j || ($i != $max)))
          $this->addAlignment($array,$x,$y);
    }

    //reserved space for version information
    if($version >= 7) for($i = $size - 11; $i < $size - 8; $i++) for($j = 0; $j < 6; $j++) $this->addSymetric($array,$i,$j,2);

    return $array;
  }
  /**
   *  Apply a mask.
   *  @param array $array  Array to apply the mask on.
   *  @param int $pattern  The mask pattern to use (0..7).
   *  @return array  The array with the mask applied.
   */
  public function arrayMask($array,$pattern){
    switch($pattern){
      case 0: $mask = [[1,0],[0,1]]; break;
      case 1: $mask = [[1,0]]; break;
      case 2: $mask = [[1],[0],[0]]; break;
      case 3: $mask = [[1,0,0],[0,0,1],[0,1,0]]; break;
      case 4: $mask = [[1,1,0,0],[1,1,0,0],[1,1,0,0],[0,0,1,1],[0,0,1,1],[0,0,1,1]]; break;
      case 5: $mask = [[1,1,1,1,1,1],[1,0,0,0,0,0],[1,0,0,1,0,0],[1,0,1,0,1,0],[1,0,0,1,0,0],[1,0,0,0,0,0]]; break;
      case 6: $mask = [[1,1,1,1,1,1],[1,1,1,0,0,0],[1,1,0,1,1,0],[1,0,1,0,1,0],[1,0,1,1,0,1],[1,0,0,0,1,1]]; break;
      case 7: $mask = [[1,0,1,0,1,0],[0,0,0,1,1,1],[1,0,0,0,1,1],[0,1,0,1,0,1],[1,1,1,0,0,0],[0,1,1,1,0,0]]; break;
    }
    $w = count($mask);
    $h = count($mask[0]);

    foreach($array as $x => &$column) foreach($column as $y => &$bit) if($bit <= 1) $bit = $bit ^ $mask[$x % $w][$y % $h];
    unset($column,$bit);
    return $array;
  }
  /**
   *  Mask score.
   *  @param array $array  Array with mask applied.
   *  @return int  Score (higher = worse).
   */
  public function arrayMaskScore($array){
    $score = 0;
    $size = count($array);

    $rows = $cols = '';
    for($y = 0; $y < $size; $y++){
      for($x = 1; $x < $size; $x++) $rows .= $array[$x][$y] & 1;
      $rows .= ';';
    }
    for($x = 0; $x < $size; $x++){
      for($y = 1; $y < $size; $y++) $rows .= $array[$x][$y] & 1;
      $cols .= ';';
    }

    foreach([$rows,$cols] as $str){
      if(preg_match_all('/(0{5,}|1{5,})/',$str,$matches)) foreach($matches[1] as $match) $score += strlen($match) - 2; //same color on line/in column
      $score += preg_match_all('/1011101/',$str,$matches) * 40; //alignment pattern look-a-like
    }

    $count = 0;
    for($x = 0; $x < $size; $x++){
      for($y = 1; $y < $size; $y++){
        if($color = $array[$x][$y] & 1) $count++;
        if(($x < $size - 1) && ($y < $size - 1) && ($array[$x + 1][$y] & 1 == $color) && ($array[$x][$y + 1] & 1 == $color) && ($array[$x + 1][$y + 1] & 1 == $color)) $score += 3;
      }
    }
    $k = abs(round($count * 100 / pow($size,2)) - 50) - 5;
    if($k > 0) $score += $k * 10;

    return $score;
  }
  /**
   *  Format information.
   *  Including error correction bits.
   *  @param string $ecc  The error correction level.
   *  @param int $pattern  The mask pattern (0..7).
   *  @return string  Bit string (16 bit).
   */
  public function formatInformation($ecc,$pattern){
    $format_information = [
      0x5412,0x5125,0x5e7c,0x5b4b,0x45f9,0x40ce,0x4f97,0x4aa0,0x77c4,0x72f3,0x7daa,0x789d,0x662f,0x6318,0x6c41,0x6976,
      0x1689,0x13be,0x1ce7,0x19d0,0x0762,0x0255,0x0d0c,0x083b,0x355f,0x3068,0x3f31,0x3a06,0x24b4,0x2183,0x2eda,0x2bed
    ];
    return $this->bin($format_information[(bindec($ecc) << 3) + $pattern],15);
  }
  /**
   *  Version information.
   *  Including error correction bits.
   *  @param int $version  QR version (1..40).
   *  @return string  Bit string (18 bit) (empty if not applicable to this version).
   */
  public function versionInformation($version){
    if($version < 7) return null;
    $version_information = [
       7 => 0x07c94, 8 => 0x085bc,
       9 => 0x09a99,10 => 0x0a4d3,11 => 0x0bbf6,12 => 0x0c762,13 => 0x0d847,14 => 0x0e60d,15 => 0x0f928,16 => 0x10b78,
      17 => 0x1145d,18 => 0x12a17,19 => 0x13532,20 => 0x149a6,21 => 0x15683,22 => 0x168c9,23 => 0x177ec,24 => 0x18ec4,
      25 => 0x191e1,26 => 0x1afab,27 => 0x1b08e,28 => 0x1cc1a,29 => 0x1d33f,30 => 0x1ed75,31 => 0x1f250,32 => 0x209d5,
      33 => 0x216f0,34 => 0x228ba,35 => 0x2379f,36 => 0x24b0b,37 => 0x2542e,38 => 0x26a64,39 => 0x27541,40 => 0x28c69
    ];
    return $this->bin($version_information[$version],18);
  }
  /**
   *  Creates a QR array.
   *  Two dimensional array where each item matches a bit/dot (0 = white, 1 = black).
   *  @param string $data  Data to encode.
   *  @param string $ecc  The error correction level to use.
   *  @param int $version  The version for the QR code (1..40). If empty the smallest size/lowest version possible will be selected.
   *  @return array
   */
  public function dataArray($data,$ecc = null,$version = null){
    $data = (string)$data;
    if(!$ecc) $ecc = $this->ecc;
    if(!$version) $version = $this->version;

    $mode = $this->determineMode($data);
    $encoded_data = $this->encodeData($data,$mode);

    //detect & check capacity
    if(!$version) while(++$version <= 40)
      if($this->versionDataCapacity($version,$ecc) << 3 >= strlen($mode . $this->bin(strlen($data),$this->lenLen($version,$mode)) . $encoded_data)) break;
    if(($version < 1) || ($version > 40)) throw new \Exception("Version '$version' out of range (1-40)");
    $data_capacity = $this->versionDataCapacity($version,$ecc);
    $data = $mode . $this->bin(strlen($data),$this->lenLen($version,$mode)) . $encoded_data;
    $data_size = ceil(strlen($data) / 8); //byte
    if($data_capacity < $data_size) throw new \Exception("Capacity too small for data ($data_capacity/$data_size bytes)");

    //terminator + padding
    $data .= '0000';
    if($rest = strlen($data) % 8) $data .= $this->bin(0,8 - $rest);
    $data .= str_repeat('1110110000010001',ceil(($data_capacity - $data_size) / 2));
    $data = substr($data,0,$data_capacity << 3);

    //error correction
    $blocks = $this->versionBlockInfo($version,$ecc);
    $block_data_index = 0;
    $max_block_size = 0;
    foreach($blocks as &$block){
      $block_size = $block['c'];
      $block_data_size = $block['k'];
      $block_data = [];
      foreach(str_split(substr($data,$block_data_index << 3,$block_data_size << 3),8) as $byte) $block_data[] = bindec($byte);
      $block_data_index += $block_data_size;
      $block['d'] = $block_data;

      $ecc_coefs = $this->eccCoefs($ecc_size = $block_size - $block_data_size);
      $block_data = array_merge($block_data,array_fill($block_data_size,$ecc_size,0));
      for($data_coef = $block_data_size; $data_coef > 0; $data_coef--) if($first_byte = array_shift($block_data)){
        $first_coef = $this->gf($first_byte,true);
        foreach($block_data as $index => &$byte) if($index < $ecc_size) $byte = $this->gf($ecc_coefs[$index] + $first_coef) ^ $byte;
        unset($byte);
      }
      $block['e'] = $block_data;

      $max_block_size = max($block_size,$max_block_size);
    }
    unset($block);

    //interleaving
    if(count($blocks) == 1) foreach($blocks[0]['e'] as $byte) $data .= $this->bin($byte,8);
    else{
      $data = '';
      for($i = 0; $i < $max_block_size; $i++) foreach($blocks as $block) if($i < $block['k']) $data .= $this->bin($block['d'][$i],8);
      for($i = 0; $i < $ecc_size; $i++) foreach($blocks as $block) $data .= $this->bin($block['e'][$i],8);
    }

    //create array
    $array = $this->createArray($version);
    $size = count($array);
    $x = $size - 2;
    $y = $size - 1;
    $column = 1; //start right
    $direction = -1; //start up
    $data_size = strlen($data); //bits
    $i = 0;
    while($i < $data_size){
      if($array[$x + $column][$y] === null) $array[$x + $column][$y] = $data[$i++];
      if($column) $column = 0; //to the left
      else{
        $y += $direction;
        if(($y < 0) || ($y >= $size)){ //end, one column to the left
          if($x == 7) $x = 4; //skip timing pattern
          else $x -= 2;
          $direction *= -1;
          $y += $direction;
        }
        $column = 1; //to the right
      }
    }

    //mask
    $best_masked_array = $best_score = $best_pattern = null;
    for($pattern = 0; $pattern < 8; $pattern++){
      $masked_array = $this->arrayMask($array,$pattern);
      $score = $this->arrayMaskScore($masked_array);
      if(($best_score === null) || ($score < $best_score)){
        $best_masked_array = $masked_array;
        $best_score = $score;
        $best_pattern = $pattern;
      }
    }
    $array = $best_masked_array;

    //format information
    $format_information = $this->formatInformation($ecc,$best_pattern);
    for($i = 0; $i <= 5; $i++) $array[$i][8] = $format_information[$i];
    for($i = 7; $i <= 14; $i++) $array[$size - 15 + $i][8] = $format_information[$i];
    for($i = 0; $i <= 6; $i++) $array[8][$size - 1 - $i] = $format_information[$i];
    for($i = 9; $i <= 14; $i++) $array[8][14 - $i] = $format_information[$i];
    $array[7][8] = $format_information[6];
    $array[8][8] = $format_information[7];
    $array[8][7] = $format_information[8];

    //version information
    if($version_information = $this->versionInformation($version))
      for($i = 0; $i < 18; $i++) $this->addSymetric($array,$size - 9 - $i % 3,5 - floor($i / 3),$version_information[$i]);

    foreach($array as &$column) foreach($column as &$bit) $bit &= 1;
    unset($column,$bit);
    return($array);
  }
  /**
   *  Convert an array to an image (file).
   *  @param array $array  Array with bit information.
   *  @param string $filename  Image filename. If empty the image resource handle will be returned.
   *  @param int $resolution  Width of a bit (dot) in pixels.
   *  @return mixed  Image resource handle or file save result.
   *  @see dataArray
   */
  public function arrayImage($array,$filename = null,$resolution = null){
    if(!$resolution) $resolution = $this->resolution;
    $size = count($array) * $resolution;
    $image = imagecreate($size,$size);
    $white = imagecolorallocate($image,255,255,255);
    $black = imagecolorallocate($image,0,0,0);
    foreach($array as $x => $column){
      $x *= $resolution;
      foreach($column as $y => $bit){
        $y *= $resolution;
        imagefilledrectangle($image,$x,$y,$x + $resolution,$y + $resolution,$bit ? $black : $white);
      }
    }
    if(!$filename) return $image;
    $i = strrpos($filename,'.');
    switch(strtolower(substr($filename,$i + 1))){
      case 'bmp': $result = imagewbmp($image,$filename); break;
      case 'gif': $result = imagegif($image,$filename); break;
      case 'jpeg':
      case 'jpg': $result = imagejpeg($image,$filename); break;
      default: $result = imagepng($image,$filename);
    }
    imagedestroy($image);
    return $result;
  }
  /**
   *  Creates a QR image.
   *  @param string $data  Data to encode.
   *  @param string $filename  Image filename. If empty the image resource handle will be returned.
   *  @param string $ecc  The error correction level to use.
   *  @param int $version  The version for the QR code (1..40). If empty the smallest size/lowest version possible will be selected.
   *  @param int $resolution  Width of a bit (dot) in pixels.
   *  @return mixed  Image resource handle or file save result.
   */
  public function dataImage($data,$filename = null,$ecc = null,$version = null,$resolution = null){
    return $this->arrayImage($this->dataArray($data,$ecc,$version),$filename,$resolution);
  }
}

?>