import sys
import pyotp

# Recibe la llave secreta y el código desde PHP
if len(sys.argv) != 3:
    print("False")
    sys.exit(1)

secret = sys.argv[1]
code = sys.argv[2]

# Valida el TOTP
totp = pyotp.TOTP(secret)
if totp.verify(code):
    print("True")
else:
    print("False")
