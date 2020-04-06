<?PHP
/* Copyright 2005-2020, Lime Technology
 * Copyright 2012-2020, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
switch ($_POST['table']) {
case 't1':
  exec('for group in $(ls /sys/kernel/iommu_groups/ -1|sort -n);do echo "IOMMU group $group";for device in $(ls -1 "/sys/kernel/iommu_groups/$group"/devices/);do echo -n $\'\t\';lspci -ns "$device"|awk \'BEGIN{ORS=" "}{print "["$3"]"}\';lspci -s "$device";done;done',$groups);
  if (empty($groups)) {
    exec('lspci -n|awk \'{print "["$3"]"}\'',$iommu);
    exec('lspci',$lspci);
    $i = 0;
    foreach ($lspci as $line) echo "<tr><td>".$iommu[$i++]."</td><td>$line</td></tr>";
    $noiommu = true;
  } else {
    if (is_file("/boot/config/vfio-pci.cfg")) $file = file_get_contents("/boot/config/vfio-pci.cfg");
    $disks = (array)parse_ini_file('state/disks.ini',true);
    $devicelist = array_column($disks, 'device');
    $lines = array ();
    foreach ($devicelist as $line) {
      if (!empty($line)) {
        exec('udevadm info --path=$(udevadm info -q path /dev/'.$line.' | cut -d / -f 1-7) --query=path',$linereturn);
        preg_match_all('/..:..\../', $linereturn[0], $inuse);
        foreach ($inuse[0] as $line) {
          $lines[] = $line;
        }
        unset($inuse);
        unset($linereturn);
      }
    }
    $networks = (array)parse_ini_file('state/network.ini',true);
    $networklist = array_column($networks, 'BRNICS');
    foreach ($networklist as $line) {
      if (!empty($line)) {
        exec('readlink /sys/class/net/'.$line,$linereturn);
        preg_match_all('/..:..\../', $linereturn[0], $inuse);
        foreach ($inuse[0] as $line) {
          $lines[] = $line;
        }
        unset($inuse);
        unset($linereturn);
      }
    }
    $iommuinuse = array ();
    foreach ($lines as $pciinuse){
      $string = exec("ls /sys/kernel/iommu_groups/*/devices/0000:$pciinuse -1 -d");
      $string = substr($string,25,2);
      $iommuinuse[] = (strpos($string,'/')) ? strstr($string, '/', true) : $string;
    }
    exec('lsscsi -s',$lsscsi);
    foreach ($groups as $line) {
      if (!$line) continue;
      if ($line[0]=='I') {
        if ($spacer) echo "<tr><td colspan='2' class='thin'></td>"; else $spacer = true;
        echo "</tr><tr><td>$line:</td><td>";
        $iommu = substr($line, 12);
        $append = true;
      } else {
        $line = preg_replace("/^\t/","",$line);
        $pciaddress = substr($line, 12, 7);
        echo ($append)?"":"<tr><td></td><td>";
        exec("lspci -v -s $pciaddress", $outputvfio);
        if (preg_grep("/vfio-pci/i", $outputvfio)) {
          echo "<i class=\"fa fa-circle orb green-orb middle\" title=\"Kernel driver in use: vfio-pci\"></i>";
          $isbound = "true";
        }
        echo "</td><td>";
        if ((strpos($line, 'Host bridge') === false) && (strpos($line, 'PCI bridge') === false)) {
          if (file_exists('/sys/kernel/iommu_groups/'.$iommu.'/devices/0000:'.$pciaddress.'/reset')) echo "<i class=\"fa fa-retweet grey-orb middle\" title=\"Function Level Reset (FLR) supported.\"></i>";
          echo "</td><td>";
          echo in_array($iommu, $iommuinuse) ? ' <input type="checkbox" value="" title="In use by Unraid" disabled ' : ' <input type="checkbox" class="iommu'.$iommu.'" value="'.$pciaddress.'" ';
          echo (strpos($file, $pciaddress) !== false) ? " checked>" : ">";
        } else { echo "</td><td>"; }
        echo '</td><td title="';
        foreach ($outputvfio as $line2) echo "$line2&#10;";
        echo '">'.$line.'</td></tr>';
        unset($outputvfio);
        switch (true) {
          case (strpos($line, 'USB controller') !== false):
            if ($isbound) {
              echo '<tr><td></td><td></td><td></td><td></td><td style="padding-left: 50px;">This controller is bound to vfio, connected USB devices are not visible.</td></tr>';
            } else {
              exec('for usb_ctrl in $(find /sys/bus/usb/devices/usb* -maxdepth 0 -type l);do path="$(realpath "${usb_ctrl}")";if [[ $path == *'.$pciaddress.'* ]];then bus="$(cat "${usb_ctrl}/busnum")";lsusb -s $bus:|sort;fi;done',$getusb);
              foreach($getusb as $usbdevice) {
                echo "<tr><td></td><td></td><td></td><td></td><td style=\"padding-left: 50px;\">$usbdevice</td></tr>";
              }
              unset($getusb);
            }
            break;
          case (strpos($line, 'SATA controller') !== false):
          case (strpos($line, 'Serial Attached SCSI controller') !== false):
          case (strpos($line, 'RAID bus controller') !== false):
          case (strpos($line, 'SCSI storage controller') !== false):
          case (strpos($line, 'IDE interface') !== false):
          case (strpos($line, 'Mass storage controller') !== false):
          case (strpos($line, 'Non-Volatile memory controller') !== false):
            if ($isbound) {
              echo '<tr><td></td><td></td><td></td><td></td><td style="padding-left: 50px;">This controller is bound to vfio, connected drives are not visible.</td></tr>';
            } else {
              exec('ls -al /sys/block/sd* | grep -i "'.$pciaddress.'"',$getsata);
              exec('ls -al /sys/block/hd* | grep -i "'.$pciaddress.'"',$getsata);
              exec('ls -al /sys/block/sr* | grep -i "'.$pciaddress.'"',$getsata);
              exec('ls -al /sys/block/nvme* | grep -i "'.$pciaddress.'"',$getsata);
              foreach($getsata as $satadevice) {
                $satadevice = substr($satadevice, strrpos($satadevice, '/', -1)+1);
                $search = preg_grep('/'.$satadevice.'.*/', $lsscsi);
                foreach ($search as $deviceline) {
                  echo '<tr><td></td><td></td><td></td><td></td><td style="padding-left: 50px;">'.$deviceline.'</td></tr>';
                }
              }
              unset($search);
              unset($getsata);
            }
            break;
        }
        unset($isbound);
        $append = false;
      }
    }
    echo '<tr><td></td><td></td><td></td><td></td><td><br><input id="applycfg" type="submit" value="Bind selected to VFIO at Boot" onclick="applyCfg();" disabled '.(($noiommu) ? "style=\"display:none\"" : "").'><span id="warning"></span></td></tr>';
  }
  break;
case 't2':
  exec('cat /sys/devices/system/cpu/*/topology/thread_siblings_list|sort -nu',$pairs);
  $i = 1;
  foreach ($pairs as $line) {
    $line = preg_replace(['/(\d+)[-,](\d+)/','/(\d+)\b/'],['$1 / $2','cpu $1'],$line);
    echo "<tr><td>".(strpos($line,'/')===false?"Single":"Pair ".$i++).":</td><td>$line</td></tr>";
  }
  break;
case 't3':
  exec('lsusb|sort',$lsusb);
  foreach ($lsusb as $line) {
    list($bus,$id) = explode(':', $line, 2);
    echo "<tr><td>$bus:</td><td>".trim($id)."</td></tr>";
  }
  break;
case 't4':
  exec('lsscsi -s',$lsscsi);
  foreach ($lsscsi as $line) {
    if (strpos($line,'/dev/')===false) continue;
    echo "<tr><td>".preg_replace('/\]  +/',']</td><td>',$line)."</td></tr>";
  }
  break;
}
?>
<script>
$("input[type='checkbox']").change(function() {
  var matches = document.querySelectorAll("." + this.className);
  for (var i=0, len=matches.length|0; i<len; i=i+1|0) {
    matches[i].checked = this.checked ? true : false;
  }
  document.getElementById("applycfg").disabled=false;
});
</script>
