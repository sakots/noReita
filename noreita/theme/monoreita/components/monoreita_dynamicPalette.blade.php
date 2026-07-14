<form name="Palette">
  @if ($tool == 'neo')
    <fieldset id="fit_exp">
      <legend>FIT!</legend>
      <input class="button" type="button" value="← FIT →" onclick="appfit(0)">
    </fieldset>
    <fieldset id="fit_comp" style="display: none;">
      <legend>FIT!</legend>
      <input class="button" type="button" value="→ FIT ←" onclick="appfit(1)">
    </fieldset>
    <fieldset>
      <legend>TOOL</legend>
      <input class="button" type="button" value="左" onclick="Neo.setToolSide(true)">
      <input class="button" type="button" value="右" onclick="Neo.setToolSide(false)">
      手ぶれ
      <select onchange="Neo.setStabilizeLevel(this.value)">
        <option value="0" selected>0</option>
        <option value="1">1</option>
        <option value="2">2</option>
        <option value="3">3</option>
        <option value="4">4</option>
        <option value="5">5</option>
      </select>
    </fieldset>
  @endif
  <fieldset>
    <legend>PALETTE</legend>
    <select class="form palette_set" name="select" size="13" onChange="setPalette()" id="palnames">
      <option>一時パレット</option>
      @if ($dynp)
        {!!$dynp!!}
      @endif
    </select><br>
    <input class="button" type="button" value="一時保存" onClick="PaletteSave()"><br>
    <input class="button" type="button" value="作成" onClick="PaletteNew()">
    <input class="button" type="button" value="変更" onClick="PaletteRenew()">
    <input class="button" type="button" value="削除" onClick="PaletteDel()"><br>
    <input class="button" type="button" value="明＋" onClick="P_Effect(10)">
    <input class="button" type="button" value="明－" onClick="P_Effect(-10)">
    <input class="button" type="button" value="反転" onClick="P_Effect(255)">
  </fieldset>
  <fieldset>
    <legend>MATRIX</legend>
    <form>
      <select class="form" name="m_m">
        <option value="0">全体</option>
        <option value="1">現在</option>
        <option value="2">追加</option>
      </select>
      <input type="button" class="button" name="m_g" value="GET" onclick="PaletteMatrixGet()">
      <input type="button" class="button" name="m_h" value="SET" onclick="PaletteMatrixSet()">
      <input type="button" class="button" name="1" value=" ? " onclick="PaletteMatrixHelp()"><br>
      <textarea class="form" name="setr" rows="1" cols="13" onmouseover="this.select()"></textarea>
    </form>
  </fieldset>
  <fieldset>
    <legend>COLOR</legend>
    <input id="neo-colorPicker" class="colorPicker" type="color" oninput="Neo.setColor(this.value)">
  </fieldset>
  <fieldset>
    <legend>GRADATION</legend>
    <form name="grad">
      <input type="checkbox" name="view" onclick="showHideLayer()">
      <input type="button" class="button" value=" OK " onclick="ChangeGrad()">
      <br>
      <select class="form" name="p_st" onchange="GetPalette()">
        <option>1</option>
        <option>2</option>
        <option>3</option>
        <option>4</option>
        <option>5</option>
        <option>6</option>
        <option>7</option>
        <option>8</option>
        <option>9</option>
        <option>10</option>
        <option>11</option>
        <option>12</option>
        <option>13</option>
        <option>14</option>
      </select>
      <input class="form gradation" type="text" name="pst" size="8" onkeypress="Change_()" onchange="Change_()" maxlength="6" pattern="^[0-9a-fA-F]{6}$">
      <input type="color" class="colorPicker" onchange="ColorPickerToGradation(this, 'pst')"><br>
      <select class="form" name="p_ed" onchange="GetPalette()">
        <option>1</option>
        <option>2</option>
        <option>3</option>
        <option>4</option>
        <option>5</option>
        <option>6</option>
        <option>7</option>
        <option>8</option>
        <option>9</option>
        <option>10</option>
        <option>11</option>
        <option selected>12</option>
        <option>13</option>
        <option>14</option>
      </select>
      <input class="form gradation" type="text" name="ped" size="8" onkeypress="Change_()" onchange="Change_()" maxlength="6" pattern="^[0-9a-fA-F]{6}$">
      <input type="color" class="colorPicker" onchange="ColorPickerToGradation(this, 'ped')">
      <div id="psft" style="position:absolute;width:100px;height:30px;z-index:1;left:5px;top:10px;"></div>
    </form>
  </fieldset>
  <p class="c">DynamicPalette &copy;NoraNeko</p>
</form>
