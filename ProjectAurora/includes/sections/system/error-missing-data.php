<div class="section-content overflow-y active" data-section="error-missing-data">
    
    <div class="section-center-wrapper">

        <div class="form-container" style="text-align: center;">
            
            <h1 style="font-size: 32px; margin-bottom: 25px;">¡Uy! Faltan datos.</h1>

            <div style="
                border: 1px solid #e0e0e0; 
                border-radius: 8px; 
                padding: 20px; 
                text-align: left; 
                background-color: #fff;
                /* Quitamos margin-bottom extra ya que no hay botón debajo */
            ">
                <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #000;">Error 400: Faltan datos</h3>
                <p style="margin: 0; font-size: 14px; color: #666; line-height: 1.5;">
                    <?php 
                    echo isset($missingDataMessage) 
                        ? $missingDataMessage 
                        : 'No has completado el paso anterior antes de acceder a esta página.'; 
                    ?>
                </p>
            </div>

        </div>

    </div>

</div>