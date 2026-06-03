import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function CustomerForgotPassword() {
    return (
        <div className="mx-auto max-w-sm py-8">
            <Head title="Recuperar contraseña" />
            <h1 className="mb-2 text-2xl font-semibold">Recuperar contraseña</h1>
            <p className="mb-6 text-sm text-neutral-500">
                Te enviaremos un enlace para restablecer tu contraseña.
            </p>

            <Form action="/cuenta/recuperar" method="post" className="space-y-4">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo</Label>
                            <Input id="email" name="email" type="email" required autoFocus />
                            <InputError message={errors.email} />
                        </div>
                        <Button className="w-full" disabled={processing}>Enviar enlace</Button>
                    </>
                )}
            </Form>

            <p className="mt-4 text-center text-sm text-neutral-500">
                <Link href="/cuenta/login" className="hover:underline">Volver a iniciar sesión</Link>
            </p>
        </div>
    );
}
