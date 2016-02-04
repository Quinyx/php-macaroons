<?php

namespace Macaroons\Serializers;

use Macaroons\Utils;
use Macaroons\Macaroon;
use Macaroons\Caveat;
use Macaroons\Exceptions\InvalidMacaroonKeyException;

/**
 * Class responsible for serialization and deserialization of Base64 "binary"
 * Macaroons
 */
class BinarySerializer extends BaseSerializer
{
  /**
   * creates a binary representation of a Macaroon
   * @return string Base64 encoded string of the binary representation
   */
  public function serialize()
  {
    $packet = new Packet();
    $packet_string = $packet->packetize(
                        array(
                          'location' => $this->macaroon->getLocation(),
                          'identifier' => $this->macaroon->getIdentifier()
                        )
                      );
    foreach ($this->macaroon->getCaveats() as $caveat)
    {
      $caveatKeys = array(
                          'cid' => $caveat->getCaveatId()
                          );
      if ($caveat->getVerificationId() && $caveat->getCaveatLocation())
      {
        $caveatKeys = array_merge(
                                  $caveatKeys,
                                  array(
                                        'vid' => $caveat->getVerificationId(),
                                        'cl' => $caveat->getCaveatLocation()
                                        )
                                  );
      }
      $packet = new Packet();
      $packet_string = $packet_string . $packet->packetize($caveatKeys);
    }
    $packet = new Packet();
    $packet_string = $packet_string . $packet->packetize(array('signature' => Utils::unhexlify($this->macaroon->getSignature())));
    return Utils::base64_url_encode($packet_string);
  }

  /**
   * Creates a new Macaroon from a Base64 encoded binary serialization
   * @param  string $serialized
   * @return Macaroon
   */
  public function deserialize($serialized)
  {
    $location   = NULL;
    $identifier = NULL;
    $signature  = NULL;
    $caveats    = array();
    $decoded    = Utils::base64_url_decode($serialized);
    $index      = 0;

    while ($index < strlen($decoded))
    {
      // TOOD: Replace 4 with PACKET_PREFIX_LENGTH
      $packetLength    = hexdec(substr($decoded, $index, 4));
      $packetDataStart = $index + 4;
      $strippedPacket  = substr($decoded, $packetDataStart, $packetLength - 5);
      $packet          = new Packet();
      $packet          = $packet->decode($strippedPacket);

      switch($packet->getKey())
      {
        case 'location':
          $location = $packet->getData();
        break;
        case 'identifier':
          $identifier = $packet->getData();
        break;
        case 'signature':
          $signature = $packet->getData();
        break;
        case 'cid':
          array_push($caveats, new Caveat($packet->getData()));
        break;
        case 'vid':
          $caveat = $caveats[ count($caveats) - 1 ];
          $caveat->setVerificationId($packet->getData());
        break;
        case 'cl':
          $caveat = $caveats[ count($caveats) - 1 ];
          $caveat->setCaveatLocation($packet->getData());
        break;
        default:
          throw new InvalidMacaroonKeyException('Invalid key in binary macaroon. Macaroon may be corrupted.');
        break;
      }
      $index = $index + $packetLength;
    }
    $macaroon = new Macaroon('no_key', $identifier, $location);
    $macaroon->setCaveats($caveats);
    $macaroon->setSignature($signature);
    return $macaroon;
  }
}
